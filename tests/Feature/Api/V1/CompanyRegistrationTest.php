<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\User;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);
        RateLimiter::clear('company-registration:127.0.0.1');
        RateLimiter::clear('company-verification:127.0.0.1');
        RateLimiter::clear('company-verification-resend:127.0.0.1');
    }

    public function test_valid_registration_creates_an_isolated_pending_company_and_administrator(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/register-company', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.company.status', Company::STATUS_PENDING_VERIFICATION)
            ->assertJsonPath('data.onboarding_status', Company::ONBOARDING_PENDING_VERIFICATION)
            ->assertJsonMissingPath('token');

        $company = Company::query()->sole();
        $administrator = User::query()->sole();

        $this->assertSame($company->id, $administrator->company_id);
        $this->assertNull($administrator->email_verified_at);
        $this->assertTrue(Hash::check('StrongPassword123!', $administrator->password));
        $this->assertTrue($company->email_verification_required);
        $this->assertSame(['client', 'company_admin', 'site_supervisor'], Role::query()->where('company_id', $company->id)->orderBy('name')->pluck('name')->all());

        setPermissionsTeamId($company->id);
        $this->assertTrue($administrator->fresh()->hasRole('company_admin'));
        $this->assertCount(count(PermissionCatalog::all()), Role::query()->where('company_id', $company->id)->where('name', 'company_admin')->firstOrFail()->permissions);
        setPermissionsTeamId(null);
        Notification::assertSentTo($administrator, VerifyEmail::class);
    }

    public function test_registration_validates_duplicates_passwords_and_protected_fields(): void
    {
        $existing = Company::factory()->create(['email' => 'company@example.test']);
        User::factory()->create(['company_id' => $existing->id, 'email' => 'admin@example.test']);

        $payload = $this->payload();
        $payload['company']['email'] = ' COMPANY@EXAMPLE.TEST ';
        $payload['admin']['email'] = ' ADMIN@EXAMPLE.TEST ';
        $payload['company']['status'] = Company::STATUS_ACTIVE;
        $payload['admin']['company_id'] = $existing->id;
        $payload['admin']['role'] = 'company_admin';
        $payload['admin']['password_confirmation'] = 'DifferentPassword123!';

        $this->postJson('/api/v1/register-company', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'company.email', 'company.status', 'admin.email', 'admin.company_id',
                'admin.role', 'admin.password',
            ]);
    }

    public function test_registration_rolls_back_if_role_initialization_fails(): void
    {
        Notification::fake();
        $beforeCompanies = Company::count();
        $beforeUsers = User::count();

        $shouldFail = true;
        User::created(function (User $user) use (&$shouldFail): void {
            if ($shouldFail && $user->email === 'ali@abcconstruction.test') {
                $shouldFail = false;
                throw new RuntimeException('Simulated role initialization failure.');
            }
        });

        $this->postJson('/api/v1/register-company', $this->payload())->assertServerError();

        $this->assertSame($beforeCompanies, Company::count());
        $this->assertSame($beforeUsers, User::count());
        Notification::assertNothingSent();
    }

    public function test_signed_verification_activates_company_and_allows_login_and_company_access(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/register-company', $this->payload())->assertCreated();
        $administrator = User::query()->sole();
        $company = Company::query()->sole();

        $this->postJson('/api/v1/login', ['email' => $administrator->email, 'password' => 'StrongPassword123!'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Email verification is required before login.');

        $this->getJson($this->verificationUrl($administrator))->assertOk();

        $this->assertNotNull($administrator->fresh()->email_verified_at);
        $this->assertSame(Company::STATUS_ACTIVE, $company->fresh()->status);
        $this->assertSame(Company::ONBOARDING_COMPLETE, $company->fresh()->onboarding_status);

        $login = $this->postJson('/api/v1/login', ['email' => $administrator->email, 'password' => 'StrongPassword123!'])
            ->assertOk()
            ->assertJsonStructure(['token']);

        $this->withToken($login->json('token'))->getJson('/api/v1/company')
            ->assertOk()
            ->assertJsonPath('data.id', $company->id);
    }

    public function test_invalid_expired_and_wrong_hash_verification_links_are_rejected(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/register-company', $this->payload())->assertCreated();
        $user = User::query()->sole();

        $this->getJson("/api/v1/verify-email/{$user->id}/".sha1($user->email))->assertForbidden();
        $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->getJson($expired)->assertForbidden();
        $wrongHash = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('wrong@example.test')]);
        $this->getJson($wrongHash)->assertForbidden();

        $this->assertSame(Company::STATUS_PENDING_VERIFICATION, Company::query()->sole()->status);
    }

    public function test_replayed_link_does_not_reactivate_a_suspended_company(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/register-company', $this->payload())->assertCreated();
        $user = User::query()->sole();
        $url = $this->verificationUrl($user);
        $this->getJson($url)->assertOk();

        $user->company->update(['status' => Company::STATUS_SUSPENDED]);
        $this->getJson($url)->assertOk();

        $this->assertSame(Company::STATUS_SUSPENDED, $user->company->fresh()->status);
    }

    public function test_resend_is_enumeration_resistant_and_rate_limited(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/register-company', $this->payload())->assertCreated();
        $user = User::query()->sole();

        $message = 'If the registration is pending verification, a new verification email has been sent.';
        $this->postJson('/api/v1/resend-verification', ['email' => $user->email])->assertOk()->assertJsonPath('message', $message);
        $this->postJson('/api/v1/resend-verification', ['email' => 'unknown@example.test'])->assertOk()->assertJsonPath('message', $message);
        $this->postJson('/api/v1/resend-verification', ['email' => 'another@example.test'])->assertOk();
        $this->postJson('/api/v1/resend-verification', ['email' => 'fourth@example.test'])->assertTooManyRequests();

        Notification::assertSentToTimes($user, VerifyEmail::class, 2);
    }

    public function test_two_registrations_use_distinct_company_roles_and_restore_team_context(): void
    {
        Notification::fake();
        setPermissionsTeamId(9876);
        $this->postJson('/api/v1/register-company', $this->payload())->assertCreated();
        $this->assertSame(9876, getPermissionsTeamId());

        $second = $this->payload('Second Builders', 'second-company@example.test', 'second-admin@example.test');
        $this->postJson('/api/v1/register-company', $second)->assertCreated();
        $this->assertSame(9876, getPermissionsTeamId());

        $companies = Company::query()->orderBy('id')->get();
        $firstRole = Role::query()->where('company_id', $companies[0]->id)->where('name', 'company_admin')->firstOrFail();
        $secondRole = Role::query()->where('company_id', $companies[1]->id)->where('name', 'company_admin')->firstOrFail();
        $this->assertNotSame($firstRole->id, $secondRole->id);
    }

    public function test_registration_endpoint_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/register-company', [])->assertUnprocessable();
        }

        $this->postJson('/api/v1/register-company', [])->assertTooManyRequests();
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);
    }

    private function payload(
        string $companyName = 'ABC Construction',
        string $companyEmail = 'info@abcconstruction.test',
        string $adminEmail = 'ali@abcconstruction.test',
    ): array {
        return [
            'company' => [
                'name' => $companyName,
                'email' => $companyEmail,
                'phone' => '+923001234567',
                'address' => 'Lahore, Pakistan',
            ],
            'admin' => [
                'first_name' => 'Ali',
                'last_name' => 'Ahmed',
                'email' => $adminEmail,
                'phone' => '+923009876543',
                'password' => 'StrongPassword123!',
                'password_confirmation' => 'StrongPassword123!',
            ],
        ];
    }
}
