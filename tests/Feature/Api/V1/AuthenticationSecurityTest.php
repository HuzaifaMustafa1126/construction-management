<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'password123';

    public function test_active_user_at_active_company_can_log_in_with_compatible_response(): void
    {
        $user = $this->user();

        $this->login($user)->assertOk()
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['message', 'user', 'token'])
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_inactive_suspended_and_unknown_user_statuses_fail_closed(): void
    {
        foreach (['Inactive', 'Suspended', 'unexpected'] as $status) {
            $user = $this->user(['status' => $status]);

            $this->login($user)->assertUnauthorized()->assertExactJson($this->loginError());
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_null_user_status_is_rejected_by_database(): void
    {
        $this->expectException(QueryException::class);

        $this->user(['status' => null]);
    }

    public function test_invalid_credentials_use_generic_error_and_create_no_token(): void
    {
        $user = $this->user();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertExactJson($this->loginError());

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unknown_email_uses_same_generic_error(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'missing@example.test',
            'password' => self::PASSWORD,
        ])->assertUnauthorized()->assertExactJson($this->loginError());
    }

    public function test_unassigned_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'password' => self::PASSWORD,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->login($user)->assertUnauthorized()->assertExactJson($this->loginError());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_suspended_inactive_and_unknown_company_statuses_fail_closed(): void
    {
        foreach (['suspended', 'inactive', 'unexpected'] as $status) {
            $user = $this->user([], Company::factory()->create(['status' => $status]));

            $this->login($user)->assertUnauthorized()->assertExactJson($this->loginError());
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_soft_deleted_company_prevents_login(): void
    {
        $company = Company::factory()->create();
        $user = $this->user([], $company);
        $company->delete();

        $this->login($user)->assertUnauthorized()->assertExactJson($this->loginError());
    }

    public function test_valid_token_accesses_profile_on_versioned_and_legacy_routes(): void
    {
        $user = $this->user();
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.id', $user->id);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_revoked_token_returns_unauthorized(): void
    {
        $user = $this->user();
        $token = $this->tokenFor($user);
        $user->tokens()->delete();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_expired_token_returns_unauthorized(): void
    {
        config(['sanctum.expiration' => 480]);
        $user = $this->user();
        $token = $this->tokenFor($user);
        $user->tokens()->first()->forceFill(['created_at' => now()->subMinutes(481)])->save();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_previously_issued_token_is_rejected_after_user_suspension(): void
    {
        $user = $this->user();
        $token = $this->tokenFor($user);
        $user->update(['status' => 'Suspended']);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Account access is unavailable.']);
    }

    public function test_previously_issued_token_is_rejected_after_company_suspension(): void
    {
        $company = Company::factory()->create();
        $user = $this->user([], $company);
        $token = $this->tokenFor($user);
        $company->update(['status' => Company::STATUS_SUSPENDED]);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Company access is suspended.']);
    }

    public function test_restricted_user_can_still_revoke_current_token_by_logging_out(): void
    {
        $user = $this->user();
        $token = $this->tokenFor($user);
        $user->update(['status' => 'Suspended']);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->postJson('/api/v1/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_revokes_only_current_token(): void
    {
        $user = $this->user();
        $currentToken = $this->tokenFor($user);
        $otherToken = $user->createToken('other')->plainTextToken;

        $this->withToken($currentToken)->postJson('/api/v1/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->app['auth']->forgetGuards();
        $this->withToken($currentToken)->getJson('/api/v1/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/me')->assertOk();
    }

    public function test_login_rate_limit_applies_to_versioned_route(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/login', [
                'email' => 'missing@example.test',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/login', [
            'email' => 'missing@example.test',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_legacy_login_enforces_identical_status_rules(): void
    {
        $user = $this->user(['status' => 'Inactive']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertUnauthorized()->assertExactJson($this->loginError());
    }

    public function test_legacy_protected_route_rechecks_current_company_status(): void
    {
        $company = Company::factory()->create();
        $user = $this->user([], $company);
        $token = $this->tokenFor($user);
        $company->update(['status' => 'inactive']);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertForbidden();
    }

    public function test_cross_company_delete_still_returns_not_found_and_preserves_target(): void
    {
        $admin = $this->user(['role' => 'admin']);
        $this->grantRole($admin, 'company_admin');
        $target = $this->user([], Company::factory()->create());

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/v1/users/{$target->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_client_cannot_inject_company_id(): void
    {
        $admin = $this->user(['role' => 'admin']);
        $this->grantRole($admin, 'company_admin');

        $this->withToken($this->tokenFor($admin))->postJson('/api/v1/users', [
            'first_name' => 'Injected',
            'last_name' => 'Tenant',
            'email' => 'injected@example.test',
            'password' => self::PASSWORD,
            'role' => 'client',
            'company_id' => Company::factory()->create()->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['company_id']);

        $this->assertDatabaseMissing('users', ['email' => 'injected@example.test']);
    }

    public function test_account_rejection_does_not_leak_tenant_context(): void
    {
        $user = $this->user(['status' => 'Suspended']);

        $this->withToken($this->tokenFor($user))->getJson('/api/v1/users')->assertForbidden();

        $this->assertFalse(app(TenantContext::class)->hasCompany());
    }

    private function user(array $attributes = [], ?Company $company = null): User
    {
        $company ??= Company::factory()->create();

        return User::factory()->for($company)->create(array_merge([
            'password' => Hash::make(self::PASSWORD),
            'status' => User::STATUS_ACTIVE,
        ], $attributes));
    }

    private function login(User $user)
    {
        return $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('security-test')->plainTextToken;
    }

    private function loginError(): array
    {
        return ['message' => 'Invalid credentials or account unavailable.'];
    }
}
