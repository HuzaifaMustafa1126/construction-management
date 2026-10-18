<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesPermissionsTest extends TestCase
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
        $this->adminA = User::factory()->for($this->companyA)->create(['role' => 'admin']);
        $this->adminB = User::factory()->for($this->companyB)->create(['role' => 'admin']);
        $this->grantRole($this->adminA, 'company_admin');
        $this->grantRole($this->adminB, 'company_admin');
    }

    public function test_each_company_admin_lists_only_its_company_roles(): void
    {
        $roleA = $this->role($this->companyA, 'a_role');
        $roleB = $this->role($this->companyB, 'b_role');

        $this->withToken($this->token($this->adminA))->getJson('/api/v1/roles')
            ->assertOk()->assertJsonFragment(['id' => $roleA->id])->assertJsonMissing(['id' => $roleB->id]);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->adminB))->getJson('/api/v1/roles')
            ->assertOk()->assertJsonFragment(['id' => $roleB->id])->assertJsonMissing(['id' => $roleA->id]);
    }

    public function test_cross_company_role_show_update_and_delete_return_not_found(): void
    {
        $foreign = $this->role($this->companyB, 'foreign_role');
        $token = $this->token($this->adminA);

        $this->withToken($token)->getJson("/api/v1/roles/{$foreign->id}")->assertNotFound();
        $this->withToken($token)->putJson("/api/v1/roles/{$foreign->id}", $this->rolePayload())->assertNotFound();
        $this->withToken($token)->deleteJson("/api/v1/roles/{$foreign->id}")->assertNotFound();
        $this->assertDatabaseHas('roles', ['id' => $foreign->id]);
    }

    public function test_company_admin_can_create_role_and_duplicate_is_rejected(): void
    {
        $token = $this->token($this->adminA);
        $response = $this->withToken($token)->postJson('/api/v1/roles', $this->rolePayload())
            ->assertCreated()->assertJsonPath('data.name', 'project_viewer');

        $this->assertDatabaseHas('roles', [
            'id' => $response->json('data.id'),
            'company_id' => $this->companyA->id,
        ]);
        $this->withToken($token)->postJson('/api/v1/roles', $this->rolePayload())
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_same_custom_role_name_is_isolated_between_companies(): void
    {
        $this->withToken($this->token($this->adminA))->postJson('/api/v1/roles', $this->rolePayload())->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->adminB))->postJson('/api/v1/roles', $this->rolePayload())->assertCreated();

        $this->assertSame(2, Role::query()->where('name', 'project_viewer')->count());
    }

    public function test_company_admin_can_update_role_permissions_and_unknown_permission_is_rejected(): void
    {
        $role = $this->role($this->companyA, 'custom_role');
        $token = $this->token($this->adminA);

        $this->withToken($token)->putJson("/api/v1/roles/{$role->id}", [
            'name' => 'custom_role',
            'permissions' => ['dashboard.view', 'projects.view'],
        ])->assertOk()->assertJsonPath('data.permissions.1', 'projects.view');

        $this->withToken($token)->putJson("/api/v1/roles/{$role->id}", [
            'name' => 'custom_role',
            'permissions' => ['platform.secret'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['permissions.0']);
    }

    public function test_supervisor_and_client_cannot_manage_roles_or_view_catalog(): void
    {
        foreach (['site_supervisor', 'client'] as $roleName) {
            $user = User::factory()->for($this->companyA)->create();
            $this->grantRole($user, $roleName);
            $token = $this->token($user);

            $this->withToken($token)->postJson('/api/v1/roles', $this->rolePayload())->assertForbidden();
            $this->withToken($token)->getJson('/api/v1/permissions')->assertForbidden();
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_admin_can_view_grouped_approved_permission_catalog(): void
    {
        $this->withToken($this->token($this->adminA))->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonPath('data.users.0', 'users.view')
            ->assertJsonMissing(['platform.super_admin']);
    }

    public function test_admin_can_assign_same_company_role_atomically(): void
    {
        $target = User::factory()->for($this->companyA)->create();
        $this->role($this->companyA, 'project_viewer');

        $this->withToken($this->token($this->adminA))->putJson("/api/v1/users/{$target->id}/roles", [
            'roles' => ['project_viewer'],
        ])->assertOk()->assertJsonPath('data.roles.0', 'project_viewer');

        setPermissionsTeamId($this->companyA->id);
        $this->assertTrue($target->fresh()->hasRole('project_viewer'));
        setPermissionsTeamId(null);
    }

    public function test_cross_company_role_assignment_is_rejected_and_target_returns_not_found(): void
    {
        $targetA = User::factory()->for($this->companyA)->create();
        $targetB = User::factory()->for($this->companyB)->create();
        $this->role($this->companyB, 'foreign_only');
        $token = $this->token($this->adminA);

        $this->withToken($token)->putJson("/api/v1/users/{$targetA->id}/roles", [
            'roles' => ['foreign_only'],
        ])->assertUnprocessable();

        $this->withToken($token)->putJson("/api/v1/users/{$targetB->id}/roles", [
            'roles' => ['company_admin'],
        ])->assertNotFound();
    }

    public function test_role_assignment_rejects_client_supplied_company_id(): void
    {
        $target = User::factory()->for($this->companyA)->create();

        $this->withToken($this->token($this->adminA))->putJson("/api/v1/users/{$target->id}/roles", [
            'roles' => ['company_admin'],
            'company_id' => $this->companyB->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['company_id']);
    }

    public function test_limited_role_assigner_cannot_assign_a_more_privileged_role(): void
    {
        $assigner = User::factory()->for($this->companyA)->create();
        $limitedRole = $this->role($this->companyA, 'limited_assigner');
        setPermissionsTeamId($this->companyA->id);
        $limitedRole->syncPermissions(['roles.assign']);
        $assigner->assignRole($limitedRole);
        setPermissionsTeamId(null);
        $target = User::factory()->for($this->companyA)->create();

        $this->withToken($this->token($assigner))->putJson("/api/v1/users/{$target->id}/roles", [
            'roles' => ['company_admin'],
        ])->assertForbidden();

        $this->assertDatabaseMissing('model_has_roles', [
            'model_id' => $target->id,
            'company_id' => $this->companyA->id,
        ]);
    }

    public function test_last_active_company_admin_cannot_be_removed(): void
    {
        $this->role($this->companyA, 'client');

        $this->withToken($this->token($this->adminA))->putJson("/api/v1/users/{$this->adminA->id}/roles", [
            'roles' => ['client'],
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'The last active Company Admin cannot be removed.');
    }

    public function test_protected_company_admin_role_cannot_be_updated_or_deleted(): void
    {
        setPermissionsTeamId($this->companyA->id);
        $role = Role::findByName('company_admin');
        setPermissionsTeamId(null);
        $token = $this->token($this->adminA);

        $this->withToken($token)->putJson("/api/v1/roles/{$role->id}", $this->rolePayload())->assertUnprocessable();
        $this->withToken($token)->deleteJson("/api/v1/roles/{$role->id}")->assertUnprocessable();
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_rbac_user_permissions_replace_legacy_admin_checks(): void
    {
        $supervisor = User::factory()->for($this->companyA)->create(['role' => 'admin']);
        $this->grantRole($supervisor, 'site_supervisor');
        $client = User::factory()->for($this->companyA)->create(['role' => 'admin']);
        $this->grantRole($client, 'client');

        $this->withToken($this->token($this->adminA))->getJson('/api/v1/users')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($supervisor))->getJson('/api/v1/users')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($client))->deleteJson("/api/v1/users/{$supervisor->id}")->assertForbidden();
    }

    public function test_cross_company_user_delete_remains_not_found(): void
    {
        $target = User::factory()->for($this->companyB)->create();

        $this->withToken($this->token($this->adminA))->deleteJson("/api/v1/users/{$target->id}")
            ->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_suspended_user_and_company_are_blocked_before_rbac(): void
    {
        $user = User::factory()->for($this->companyA)->create(['status' => 'Suspended']);
        $this->grantRole($user, 'company_admin');
        $this->withToken($this->token($user))->getJson('/api/v1/roles')->assertForbidden();

        $this->companyB->update(['status' => 'suspended']);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->adminB))->getJson('/api/v1/roles')->assertForbidden();
    }

    public function test_permission_team_context_does_not_leak_between_requests(): void
    {
        $roleA = $this->role($this->companyA, 'only_a');
        $roleB = $this->role($this->companyB, 'only_b');

        $this->withToken($this->token($this->adminA))->getJson('/api/v1/roles')
            ->assertJsonFragment(['id' => $roleA->id])->assertJsonMissing(['id' => $roleB->id]);
        $this->assertNull(getPermissionsTeamId());
        $this->assertFalse(app(TenantContext::class)->hasCompany());

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->adminB))->getJson('/api/v1/roles')
            ->assertJsonFragment(['id' => $roleB->id])->assertJsonMissing(['id' => $roleA->id]);
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_role_changes_create_non_sensitive_activity_logs(): void
    {
        $this->withToken($this->token($this->adminA))->postJson('/api/v1/roles', $this->rolePayload())->assertCreated();

        $this->assertDatabaseHas('activity_log', ['event' => 'role.created', 'causer_id' => $this->adminA->id]);
        $properties = json_encode(\DB::table('activity_log')->latest('id')->value('properties'));
        $this->assertStringNotContainsString('token', strtolower($properties));
        $this->assertStringNotContainsString('password', strtolower($properties));
    }

    private function role(Company $company, string $name): Role
    {
        setPermissionsTeamId($company->id);
        $role = Role::query()->firstOrCreate([
            'company_id' => $company->id,
            'name' => $name,
            'guard_name' => 'web',
        ]);
        setPermissionsTeamId(null);

        return $role;
    }

    private function token(User $user): string
    {
        return $user->createToken('rbac-test')->plainTextToken;
    }

    private function rolePayload(): array
    {
        return ['name' => 'project_viewer', 'permissions' => ['dashboard.view', 'projects.view']];
    }
}
