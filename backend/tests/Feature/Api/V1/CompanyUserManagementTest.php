<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanyUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $adminA;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->companyA = Company::factory()->create();
        $this->companyB = Company::factory()->create();
        $this->adminA = User::factory()->for($this->companyA)->create(['role' => 'admin', 'password' => 'password123']);
        $this->adminB = User::factory()->for($this->companyB)->create(['role' => 'admin']);
        $this->grantRole($this->adminA, 'company_admin');
        $this->grantRole($this->adminB, 'company_admin');
    }

    public function test_admin_lists_only_own_company_users_with_pagination(): void
    {
        User::factory()->count(3)->for($this->companyA)->create();
        $foreign = User::factory()->for($this->companyB)->create();

        $this->withToken($this->token($this->adminA))->getJson('/api/v1/users?per_page=2&page=1')
            ->assertOk()->assertJsonCount(2, 'users')->assertJsonPath('meta.total', 4)
            ->assertJsonMissing(['id' => $foreign->id]);
    }

    public function test_search_status_sort_and_tenant_aware_role_filters_work(): void
    {
        $target = User::factory()->for($this->companyA)->create([
            'name' => 'Ahmed Ali', 'email' => 'ahmed@example.test', 'status' => 'Inactive',
        ]);
        $this->grantRole($target, 'site_supervisor');
        $foreign = User::factory()->for($this->companyB)->create(['name' => 'Ahmed Foreign']);
        $this->grantRole($foreign, 'site_supervisor');

        $this->withToken($this->token($this->adminA))->getJson(
            '/api/v1/users?search=Ahmed&status=Inactive&role=site_supervisor&sort=name&direction=asc',
        )->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('users.0.id', $target->id);

        $this->withToken($this->token($this->adminA))->getJson('/api/v1/users?sort=password')
            ->assertUnprocessable()->assertJsonValidationErrors(['sort']);
    }

    public function test_admin_creates_user_with_server_company_hashed_password_and_company_role(): void
    {
        $response = $this->withToken($this->token($this->adminA))->postJson('/api/v1/users', $this->payload())
            ->assertCreated()->assertJsonPath('user.roles.0', 'site_supervisor')
            ->assertJsonMissingPath('user.company_id')->assertJsonMissingPath('user.password');

        $user = User::findOrFail($response->json('user.id'));
        $this->assertSame($this->companyA->id, $user->company_id);
        $this->assertTrue(Hash::check('StrongPassword123!', $user->password));
        $this->assertNotSame('StrongPassword123!', $user->password);
        setPermissionsTeamId($this->companyA->id);
        $this->assertTrue($user->hasRole('site_supervisor'));
        setPermissionsTeamId(null);
    }

    public function test_create_validation_duplicate_email_and_company_injection_are_rejected(): void
    {
        User::factory()->for($this->companyA)->create(['email' => 'duplicate@example.test']);
        $token = $this->token($this->adminA);

        $this->withToken($token)->postJson('/api/v1/users', array_merge($this->payload(), [
            'email' => 'invalid', 'company_id' => $this->companyB->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['email', 'company_id']);

        $this->withToken($token)->postJson('/api/v1/users', array_merge($this->payload(), [
            'email' => 'duplicate@example.test',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_admin_can_view_and_update_same_company_user_safely(): void
    {
        $target = User::factory()->for($this->companyA)->create();
        $token = $this->token($this->adminA);

        $this->withToken($token)->getJson("/api/v1/users/{$target->id}")
            ->assertOk()->assertJsonPath('user.id', $target->id)->assertJsonMissingPath('user.password');

        $this->withToken($token)->patchJson("/api/v1/users/{$target->id}", [
            'first_name' => 'Updated', 'last_name' => 'Person', 'phone' => '03001112222',
        ])->assertOk()->assertJsonPath('user.name', 'Updated Person');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Updated Person']);
    }

    public function test_update_rejects_security_fields_and_cross_company_targets_are_not_found(): void
    {
        $target = User::factory()->for($this->companyA)->create();
        $foreign = User::factory()->for($this->companyB)->create();
        $token = $this->token($this->adminA);

        $this->withToken($token)->putJson("/api/v1/users/{$target->id}", [
            'company_id' => $this->companyB->id, 'status' => 'Suspended',
            'password' => 'Changed123!', 'role' => 'admin',
        ])->assertUnprocessable()->assertJsonValidationErrors(['company_id', 'status', 'password', 'role']);
        $this->withToken($token)->getJson("/api/v1/users/{$foreign->id}")->assertNotFound();
        $this->withToken($token)->patchJson("/api/v1/users/{$foreign->id}", ['phone' => '1'])->assertNotFound();
    }

    public function test_admin_can_suspend_and_reactivate_user_and_issued_token_is_blocked_while_suspended(): void
    {
        $target = User::factory()->for($this->companyA)->create();
        $this->grantRole($target, 'client');
        $targetToken = $this->token($target);
        $adminToken = $this->token($this->adminA);

        $this->withToken($adminToken)->patchJson("/api/v1/users/{$target->id}/status", ['status' => 'Suspended'])
            ->assertOk()->assertJsonPath('user.status', 'Suspended');
        $this->app['auth']->forgetGuards();
        $this->withToken($targetToken)->getJson('/api/v1/me')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($adminToken)->patchJson("/api/v1/users/{$target->id}/status", ['status' => 'Active'])
            ->assertOk()->assertJsonPath('user.status', 'Active');
        $this->app['auth']->forgetGuards();
        $this->withToken($targetToken)->getJson('/api/v1/me')->assertOk();
    }

    public function test_last_active_company_admin_cannot_be_suspended(): void
    {
        $this->withToken($this->token($this->adminA))
            ->patchJson("/api/v1/users/{$this->adminA->id}/status", ['status' => 'Inactive'])
            ->assertUnprocessable();
        $this->assertSame(User::STATUS_ACTIVE, $this->adminA->fresh()->status);
    }

    public function test_supervisor_and_client_cannot_manage_users(): void
    {
        foreach (['site_supervisor', 'client'] as $role) {
            $user = User::factory()->for($this->companyA)->create();
            $this->grantRole($user, $role);
            $token = $this->token($user);
            $this->withToken($token)->getJson('/api/v1/users')->assertForbidden();
            $this->withToken($token)->postJson('/api/v1/users', $this->payload())->assertForbidden();
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_permitted_user_is_soft_deleted_and_cannot_login_or_use_existing_token(): void
    {
        $target = User::factory()->for($this->companyA)->create(['password' => 'password123']);
        $this->grantRole($target, 'client');
        $issuedToken = $this->token($target);

        $this->withToken($this->token($this->adminA))->deleteJson("/api/v1/users/{$target->id}")
            ->assertOk();
        $this->assertSoftDeleted('users', ['id' => $target->id]);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/login', ['email' => $target->email, 'password' => 'password123'])->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($issuedToken)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_cross_company_delete_is_not_found_and_last_admin_is_protected(): void
    {
        $foreign = User::factory()->for($this->companyB)->create();
        $this->withToken($this->token($this->adminA))->deleteJson("/api/v1/users/{$foreign->id}")->assertNotFound();
        $this->assertNotSoftDeleted('users', ['id' => $foreign->id]);

        $this->withToken($this->token($this->adminA))
            ->deleteJson("/api/v1/users/{$this->adminA->id}")->assertForbidden();
        $this->assertNotSoftDeleted('users', ['id' => $this->adminA->id]);
    }

    public function test_user_updates_own_profile_but_cannot_change_security_fields(): void
    {
        $token = $this->token($this->adminA);
        $this->withToken($token)->putJson('/api/v1/me', ['first_name' => 'Self', 'last_name' => 'Updated'])
            ->assertOk()->assertJsonPath('user.name', 'Self Updated');
        $this->withToken($token)->putJson('/api/v1/me', [
            'company_id' => $this->companyB->id, 'status' => 'Suspended', 'role' => 'client',
        ])->assertUnprocessable()->assertJsonValidationErrors(['company_id', 'status', 'role']);
    }

    public function test_password_change_requires_current_password_and_confirmation_and_revokes_other_sessions(): void
    {
        $current = $this->token($this->adminA);
        $other = $this->token($this->adminA);

        $this->withToken($current)->putJson('/api/v1/me/password', [
            'current_password' => 'wrong', 'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnprocessable()->assertJsonValidationErrors(['current_password']);
        $this->withToken($current)->putJson('/api/v1/me/password', [
            'current_password' => 'password123', 'password' => 'NewPassword123!',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
        $this->withToken($current)->putJson('/api/v1/me/password', [
            'current_password' => 'password123', 'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $this->adminA->fresh()->password));
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/v1/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/v1/me')->assertOk();
    }

    public function test_activity_logs_record_changes_without_secrets(): void
    {
        $this->withToken($this->token($this->adminA))->postJson('/api/v1/users', $this->payload())->assertCreated();
        $properties = \DB::table('activity_log')->latest('id')->value('properties');
        $this->assertStringNotContainsString('StrongPassword123!', $properties);
        $this->assertStringNotContainsString('password', strtolower($properties));
        $this->assertStringNotContainsString('token', strtolower($properties));
    }

    private function payload(): array
    {
        return [
            'first_name' => 'Ahmed', 'last_name' => 'Ali', 'email' => 'ahmed@example.test',
            'phone' => '03001234567', 'password' => 'StrongPassword123!', 'role' => 'site_supervisor',
        ];
    }

    private function token(User $user): string
    {
        return $user->createToken('user-management-test')->plainTextToken;
    }
}
