<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $adminA;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tenant_test_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
        });

        $this->companyA = Company::factory()->create(['name' => 'Company A']);
        $this->companyB = Company::factory()->create(['name' => 'Company B']);
        $this->adminA = User::factory()->for($this->companyA)->create(['role' => 'admin']);
        $this->adminB = User::factory()->for($this->companyB)->create(['role' => 'admin']);
        $this->grantRole($this->adminA, 'company_admin');
        $this->grantRole($this->adminB, 'company_admin');
    }

    public function test_each_company_admin_lists_only_their_own_company_users(): void
    {
        $userA = User::factory()->for($this->companyA)->create();
        $userB = User::factory()->for($this->companyB)->create();

        $responseA = $this->withToken($this->tokenFor($this->adminA))->getJson('/api/v1/users');
        $responseA->assertOk()
            ->assertJsonFragment(['id' => $userA->id])
            ->assertJsonMissing(['id' => $userB->id]);

        $serializedUserA = collect($responseA->json('users'))->firstWhere('id', $userA->id);
        $this->assertSame($this->companyA->id, $serializedUserA['company_id']);
        $this->assertSame([
            'id' => $this->companyA->id,
            'name' => $this->companyA->name,
            'slug' => $this->companyA->slug,
        ], $serializedUserA['company']);
        $this->assertTrue(
            collect($responseA->json('users'))->every(
                fn (array $user) => $user['company_id'] === $this->companyA->id,
            ),
        );

        $this->app['auth']->forgetGuards();

        $responseB = $this->withToken($this->tokenFor($this->adminB))->getJson('/api/v1/users');
        $responseB->assertOk()
            ->assertJsonFragment(['id' => $userB->id])
            ->assertJsonMissing(['id' => $userA->id]);

        $serializedUserB = collect($responseB->json('users'))->firstWhere('id', $userB->id);
        $this->assertSame($this->companyB->id, $serializedUserB['company_id']);
        $this->assertSame([
            'id' => $this->companyB->id,
            'name' => $this->companyB->name,
            'slug' => $this->companyB->slug,
        ], $serializedUserB['company']);
    }

    public function test_admin_cannot_delete_another_company_user(): void
    {
        $userB = User::factory()->for($this->companyB)->create();

        $this->withToken($this->tokenFor($this->adminA))
            ->deleteJson("/api/v1/users/{$userB->id}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'User not found.']);

        $this->assertDatabaseHas('users', ['id' => $userB->id]);
    }

    public function test_submitted_company_id_is_rejected_and_cannot_override_context(): void
    {
        $this->withToken($this->tokenFor($this->adminA))
            ->postJson('/api/v1/users', array_merge($this->userPayload(), [
                'company_id' => $this->companyB->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);

        $this->assertDatabaseMissing('users', ['email' => 'new.user@example.com']);
    }

    public function test_created_user_is_automatically_assigned_to_admin_company(): void
    {
        $this->withToken($this->tokenFor($this->adminA))
            ->postJson('/api/v1/users', $this->userPayload())
            ->assertCreated()
            ->assertJsonMissingPath('user.company_id');

        $this->assertDatabaseHas('users', [
            'email' => 'new.user@example.com',
            'company_id' => $this->companyA->id,
        ]);
    }

    public function test_unassigned_user_is_denied_company_scoped_operations(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/users')
            ->assertForbidden()
            ->assertExactJson(['message' => 'A company assignment is required.']);
    }

    public function test_suspended_company_is_denied_company_scoped_operations(): void
    {
        $company = Company::factory()->suspended()->create();
        $admin = User::factory()->for($company)->create(['role' => 'admin']);

        $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/users')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Company access is suspended.']);
    }

    public function test_unauthenticated_user_management_requests_return_unauthorized(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
        $this->postJson('/api/v1/users', $this->userPayload())->assertUnauthorized();
        $this->deleteJson('/api/v1/users/999')->assertUnauthorized();
    }

    public function test_supervisor_and_client_cannot_manage_users(): void
    {
        foreach (['supervisor', 'client'] as $role) {
            $user = User::factory()->for($this->companyA)->create(['role' => $role]);
            $this->grantRole($user, $role === 'supervisor' ? 'site_supervisor' : 'client');
            $token = $this->tokenFor($user);

            $this->withToken($token)->getJson('/api/v1/users')->assertForbidden();
            $this->withToken($token)->postJson('/api/v1/users', $this->userPayload())->assertForbidden();
        }
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $this->withToken($this->tokenFor($this->adminA))
            ->deleteJson("/api/v1/users/{$this->adminA->id}")
            ->assertForbidden()
            ->assertExactJson(['message' => 'You cannot delete your own account.']);
    }

    public function test_legacy_routes_apply_identical_tenant_restrictions(): void
    {
        $userA = User::factory()->for($this->companyA)->create();
        $userB = User::factory()->for($this->companyB)->create();
        $token = $this->tokenFor($this->adminA);

        $legacyList = $this->withToken($token)->getJson('/api/users');
        $legacyList
            ->assertOk()
            ->assertJsonFragment(['id' => $userA->id])
            ->assertJsonMissing(['id' => $userB->id]);

        $legacyUserA = collect($legacyList->json('users'))->firstWhere('id', $userA->id);
        $this->assertSame($this->companyA->id, $legacyUserA['company_id']);
        $this->assertSame([
            'id' => $this->companyA->id,
            'name' => $this->companyA->name,
            'slug' => $this->companyA->slug,
        ], $legacyUserA['company']);

        $this->withToken($token)->deleteJson("/api/users/{$userB->id}")
            ->assertNotFound();

        $this->withToken($token)->postJson('/api/users', array_merge($this->userPayload(), [
            'company_id' => $this->companyB->id,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);
    }

    public function test_tenant_context_does_not_leak_between_requests(): void
    {
        $this->withToken($this->tokenFor($this->adminA))->getJson('/api/v1/users')->assertOk();
        $this->assertFalse(app(TenantContext::class)->hasCompany());
        $this->app['auth']->forgetGuards();

        $unassigned = User::factory()->create(['role' => 'admin']);
        $this->withToken($this->tokenFor($unassigned))->getJson('/api/v1/users')->assertForbidden();
        $this->assertFalse(app(TenantContext::class)->hasCompany());
        $this->app['auth']->forgetGuards();

        $this->withToken($this->tokenFor($this->adminB))->getJson('/api/v1/users')->assertOk();
        $this->assertFalse(app(TenantContext::class)->hasCompany());
    }

    public function test_generic_tenant_model_fails_closed_without_context(): void
    {
        $this->expectException(LogicException::class);

        TenantTestRecord::query()->get();
    }

    public function test_explicit_context_scopes_generic_queries_and_assigns_company(): void
    {
        $context = app(TenantContext::class);

        $recordA = $context->run($this->companyA, function () {
            return TenantTestRecord::create(['name' => 'A record']);
        });
        $recordB = $context->run($this->companyB, function () {
            return TenantTestRecord::create(['name' => 'B record']);
        });

        $this->assertSame($this->companyA->id, $recordA->company_id);
        $this->assertSame($this->companyB->id, $recordB->company_id);
        $this->assertFalse($context->hasCompany());

        $visibleIds = $context->run(
            $this->companyA,
            fn () => TenantTestRecord::query()->pluck('id')->all(),
        );

        $this->assertSame([$recordA->id], $visibleIds);
    }

    public function test_generic_tenant_model_rejects_cross_company_creation(): void
    {
        $this->expectException(LogicException::class);

        app(TenantContext::class)->run($this->companyA, function (): void {
            TenantTestRecord::create([
                'name' => 'Invalid record',
                'company_id' => $this->companyB->id,
            ]);
        });
    }

    public function test_login_me_logout_and_public_health_remain_functional(): void
    {
        $user = User::factory()->for($this->companyA)->create([
            'password' => 'password123',
        ]);

        $login = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['token']);

        $token = $login->json('token');
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $this->withToken($token)->postJson('/api/v1/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/health')->assertOk();
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('tenant-test')->plainTextToken;
    }

    private function userPayload(): array
    {
        return [
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'new.user@example.com',
            'phone' => '03001234567',
            'password' => 'secure-password',
            'role' => 'client',
            'department' => 'Operations',
        ];
    }
}

class TenantTestRecord extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $table = 'tenant_test_records';

    protected $guarded = [];
}
