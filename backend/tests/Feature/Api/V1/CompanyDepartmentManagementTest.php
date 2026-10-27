<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyDepartmentManagementTest extends TestCase
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
        $this->adminA = User::factory()->for($this->companyA)->create(['status' => 'Active']);
        $this->adminB = User::factory()->for($this->companyB)->create(['status' => 'Active']);
        $this->grantRole($this->adminA, 'company_admin');
        $this->grantRole($this->adminB, 'company_admin');
    }

    public function test_company_profile_is_safe_tenant_scoped_and_updatable(): void
    {
        $token = $this->token($this->adminA);
        $this->withToken($token)->getJson('/api/v1/company')->assertOk()
            ->assertJsonPath('data.id', $this->companyA->id)
            ->assertJsonMissingPath('data.subscription_status');

        $this->withToken($token)->patchJson('/api/v1/company', [
            'city' => 'Lahore', 'state' => 'Punjab', 'postal_code' => '54000',
            'website' => 'https://example.test',
        ])->assertOk()->assertJsonPath('data.city', 'Lahore');
        $this->assertDatabaseHas('companies', ['id' => $this->companyA->id, 'state' => 'Punjab']);

        $this->withToken($token)->patchJson('/api/v1/company', [
            'id' => $this->companyB->id, 'status' => 'Suspended', 'slug' => 'injected',
        ])->assertUnprocessable()->assertJsonValidationErrors(['id', 'status', 'slug']);
    }

    public function test_company_profile_permissions_are_enforced(): void
    {
        $supervisor = User::factory()->for($this->companyA)->create(['status' => 'Active']);
        $this->grantRole($supervisor, 'site_supervisor');
        $token = $this->token($supervisor);
        $this->withToken($token)->getJson('/api/v1/company')->assertForbidden();
        $this->withToken($token)->patchJson('/api/v1/company', ['city' => 'Karachi'])->assertForbidden();
    }

    public function test_department_crud_is_tenant_scoped_and_rejects_company_injection(): void
    {
        $token = $this->token($this->adminA);
        $response = $this->withToken($token)->postJson('/api/v1/departments', [
            'name' => 'Engineering', 'code' => 'ENG', 'description' => 'Delivery team',
        ])->assertCreated()->assertJsonPath('data.name', 'Engineering');
        $id = $response->json('data.id');
        $this->assertDatabaseHas('departments', ['id' => $id, 'company_id' => $this->companyA->id]);

        $this->withToken($token)->postJson('/api/v1/departments', [
            'name' => 'Injected', 'code' => 'INJ', 'company_id' => $this->companyB->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['company_id']);
        $this->withToken($token)->patchJson("/api/v1/departments/{$id}", ['name' => 'Operations'])
            ->assertOk()->assertJsonPath('data.name', 'Operations');

        $foreign = Department::factory()->for($this->companyB)->create();
        $this->withToken($token)->getJson("/api/v1/departments/{$foreign->id}")->assertNotFound();
        $this->withToken($token)->patchJson("/api/v1/departments/{$foreign->id}", ['name' => 'Nope'])->assertNotFound();
        $this->withToken($token)->deleteJson("/api/v1/departments/{$foreign->id}")->assertNotFound();
    }

    public function test_department_uniqueness_is_case_insensitive_per_company_and_reusable_after_delete(): void
    {
        $department = Department::factory()->for($this->companyA)->create(['name' => 'Finance', 'code' => 'FIN']);
        Department::factory()->for($this->companyB)->create(['name' => 'Finance', 'code' => 'FIN']);
        $token = $this->token($this->adminA);

        $this->withToken($token)->postJson('/api/v1/departments', ['name' => 'finance', 'code' => 'fin'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'code']);
        $this->withToken($token)->deleteJson("/api/v1/departments/{$department->id}")->assertOk();
        $this->assertSoftDeleted('departments', ['id' => $department->id]);
        $this->withToken($token)->postJson('/api/v1/departments', ['name' => 'Finance', 'code' => 'FIN'])
            ->assertCreated();
    }

    public function test_department_listing_filters_sorts_and_never_leaks_tenants(): void
    {
        Department::factory()->for($this->companyA)->create(['name' => 'Zulu', 'code' => 'ZUL', 'status' => 'Inactive']);
        $match = Department::factory()->for($this->companyA)->create(['name' => 'Alpha Site', 'code' => 'ALP']);
        $foreign = Department::factory()->for($this->companyB)->create(['name' => 'Alpha Foreign']);

        $this->withToken($this->token($this->adminA))->getJson('/api/v1/departments?search=Alpha&status=Active&sort=name')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $match->id)
            ->assertJsonMissing(['id' => $foreign->id]);
    }

    public function test_assignment_requires_active_same_company_department_and_supports_unassign(): void
    {
        $user = User::factory()->for($this->companyA)->create(['status' => 'Active']);
        $active = Department::factory()->for($this->companyA)->create();
        $inactive = Department::factory()->for($this->companyA)->create(['status' => 'Inactive']);
        $foreign = Department::factory()->for($this->companyB)->create();
        $token = $this->token($this->adminA);

        $this->withToken($token)->putJson("/api/v1/users/{$user->id}/department", ['department_id' => $active->id])
            ->assertOk()->assertJsonPath('data.department_id', $active->id);
        $this->assertSame($active->id, $user->fresh()->department_id);
        $this->withToken($token)->putJson("/api/v1/users/{$user->id}/department", ['department_id' => $inactive->id])
            ->assertUnprocessable();
        $this->withToken($token)->putJson("/api/v1/users/{$user->id}/department", ['department_id' => $foreign->id])
            ->assertNotFound();
        $this->withToken($token)->putJson("/api/v1/users/{$user->id}/department", ['department_id' => null])
            ->assertOk()->assertJsonPath('data.department_id', null);
    }

    public function test_department_can_be_deactivated_and_reactivated_without_changing_users(): void
    {
        $department = Department::factory()->for($this->companyA)->create();
        $member = User::factory()->for($this->companyA)->create([
            'status' => 'Active', 'department_id' => $department->id,
        ]);
        $token = $this->token($this->adminA);

        $this->withToken($token)->patchJson("/api/v1/departments/{$department->id}/status", ['status' => 'Inactive'])
            ->assertOk()->assertJsonPath('data.status', 'Inactive');
        $this->assertSame('Active', $member->fresh()->status);
        $this->assertSame($department->id, $member->department_id);
        $this->withToken($token)->patchJson("/api/v1/departments/{$department->id}/status", ['status' => 'Active'])
            ->assertOk()->assertJsonPath('data.status', 'Active');
    }

    public function test_cross_company_user_assignment_is_not_found_and_context_does_not_leak(): void
    {
        $departmentA = Department::factory()->for($this->companyA)->create();
        $departmentB = Department::factory()->for($this->companyB)->create();
        $foreignUser = User::factory()->for($this->companyB)->create(['status' => 'Active']);

        $this->withToken($this->token($this->adminA))
            ->putJson("/api/v1/users/{$foreignUser->id}/department", ['department_id' => $departmentA->id])
            ->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->adminB))->getJson('/api/v1/departments')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $departmentB->id)
            ->assertJsonMissing(['id' => $departmentA->id]);
    }

    public function test_soft_delete_preserves_user_assignment_and_history_but_blocks_new_assignment(): void
    {
        $department = Department::factory()->for($this->companyA)->create();
        $assigned = User::factory()->for($this->companyA)->create(['status' => 'Active', 'department_id' => $department->id]);
        $other = User::factory()->for($this->companyA)->create(['status' => 'Active']);
        $token = $this->token($this->adminA);

        $this->withToken($token)->deleteJson("/api/v1/departments/{$department->id}")->assertOk();
        $this->assertSame($department->id, $assigned->fresh()->department_id);
        $this->assertNotNull($assigned->fresh()->department()->first());
        $this->withToken($token)->putJson("/api/v1/users/{$other->id}/department", ['department_id' => $department->id])
            ->assertNotFound();
    }

    public function test_department_users_and_user_filter_are_tenant_scoped_and_include_details(): void
    {
        $department = Department::factory()->for($this->companyA)->create(['name' => 'Safety', 'code' => 'SAFE']);
        $member = User::factory()->for($this->companyA)->create(['status' => 'Active', 'department_id' => $department->id, 'department' => 'Legacy']);
        User::factory()->for($this->companyA)->create(['status' => 'Active']);
        $token = $this->token($this->adminA);

        $this->withToken($token)->getJson("/api/v1/departments/{$department->id}/users")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('users.0.id', $member->id)
            ->assertJsonPath('users.0.department', 'Legacy')->assertJsonPath('users.0.department_details.id', $department->id);
        $this->withToken($token)->getJson("/api/v1/departments/{$department->id}")
            ->assertOk()->assertJsonPath('data.employee_count', 1);
        $this->withToken($token)->getJson("/api/v1/users?department_id={$department->id}")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('users.0.id', $member->id);
    }

    public function test_supervisor_can_view_but_cannot_mutate_and_client_cannot_view(): void
    {
        $department = Department::factory()->for($this->companyA)->create();
        foreach (['site_supervisor', 'client'] as $role) {
            $user = User::factory()->for($this->companyA)->create(['status' => 'Active']);
            $this->grantRole($user, $role);
            $token = $this->token($user);
            $this->withToken($token)->getJson('/api/v1/departments')
                ->assertStatus($role === 'site_supervisor' ? 200 : 403);
            $this->withToken($token)->postJson('/api/v1/departments', ['name' => 'No', 'code' => 'NO'])->assertForbidden();
            $this->withToken($token)->putJson("/api/v1/users/{$user->id}/department", ['department_id' => $department->id])->assertForbidden();
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_activity_log_contains_tenant_context_without_sensitive_data(): void
    {
        $this->withToken($this->token($this->adminA))->postJson('/api/v1/departments', [
            'name' => 'Procurement', 'code' => 'PROC',
        ])->assertCreated();
        $log = DB::table('activity_log')->latest('id')->first();
        $this->assertSame('department.created', $log->event);
        $this->assertStringContainsString('company_id', $log->properties);
        $this->assertStringNotContainsString('token', strtolower($log->properties));
        $this->assertStringNotContainsString('password', strtolower($log->properties));
    }

    private function token(User $user): string
    {
        return $user->createToken('department-management-test')->plainTextToken;
    }
}
