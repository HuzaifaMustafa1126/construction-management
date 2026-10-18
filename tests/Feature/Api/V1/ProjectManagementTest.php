<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $adminA;

    private User $adminB;

    private User $supervisorA;

    private User $clientA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->companyA = Company::factory()->create(['currency' => 'PKR']);
        $this->companyB = Company::factory()->create(['currency' => 'USD']);
        $this->adminA = User::factory()->for($this->companyA)->create(['status' => 'Active']);
        $this->adminB = User::factory()->for($this->companyB)->create(['status' => 'Active']);
        $this->supervisorA = User::factory()->for($this->companyA)->create(['status' => 'Active']);
        $this->clientA = User::factory()->for($this->companyA)->create(['status' => 'Active']);
        $this->grantRole($this->adminA, 'company_admin');
        $this->grantRole($this->adminB, 'company_admin');
        $this->grantRole($this->supervisorA, 'site_supervisor');
        $this->grantRole($this->clientA, 'client');
    }

    public function test_admin_creates_tenant_project_with_company_currency_and_protected_defaults(): void
    {
        $response = $this->withToken($this->token($this->adminA))->postJson('/api/v1/projects', $this->payload())
            ->assertCreated()->assertJsonPath('data.code', 'PRJ-001')->assertJsonPath('data.currency', 'PKR')
            ->assertJsonPath('data.status', 'planning')->assertJsonPath('data.progress_percentage', 0);
        $this->assertDatabaseHas('projects', [
            'id' => $response->json('data.id'), 'company_id' => $this->companyA->id,
            'created_by' => $this->adminA->id, 'currency' => 'PKR',
        ]);

        $this->withToken($this->token($this->adminA))->postJson('/api/v1/projects', array_merge($this->payload(), [
            'code' => 'INJECT', 'company_id' => $this->companyB->id, 'currency' => 'USD',
            'status' => 'completed', 'progress_percentage' => 100, 'members' => [['user_id' => $this->adminA->id]],
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'company_id', 'currency', 'status', 'progress_percentage', 'members',
        ]);
    }

    public function test_project_validation_enforces_case_insensitive_tenant_code_budget_and_dates(): void
    {
        $this->project($this->companyA, $this->adminA, ['code' => 'SITE-01']);
        $this->project($this->companyB, $this->adminB, ['code' => 'SITE-01']);
        $token = $this->token($this->adminA);

        $this->withToken($token)->postJson('/api/v1/projects', array_merge($this->payload(), ['code' => 'site-01']))
            ->assertUnprocessable()->assertJsonValidationErrors(['code']);
        $this->withToken($token)->postJson('/api/v1/projects', array_merge($this->payload(), [
            'code' => 'DATES', 'budget' => -1, 'start_date' => '2026-12-01', 'expected_end_date' => '2026-11-01',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['budget', 'expected_end_date']);
    }

    public function test_admin_list_search_filter_and_tenant_isolation_work(): void
    {
        $match = $this->project($this->companyA, $this->adminA, ['name' => 'Alpha Tower', 'code' => 'ALPHA', 'status' => 'active']);
        $this->project($this->companyA, $this->adminA, ['name' => 'Beta', 'code' => 'BETA']);
        $foreign = $this->project($this->companyB, $this->adminB, ['name' => 'Alpha Foreign', 'code' => 'FOREIGN', 'status' => 'active']);

        $this->withToken($this->token($this->adminA))->getJson('/api/v1/projects?search=Alpha&status=active&sort=name')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $match->id)
            ->assertJsonMissing(['id' => $foreign->id]);
        $this->withToken($this->token($this->adminA))->getJson('/api/v1/projects?per_page=101')
            ->assertUnprocessable()->assertJsonValidationErrors(['per_page']);
    }

    public function test_supervisor_and_client_see_only_assigned_projects(): void
    {
        $assigned = $this->project($this->companyA, $this->adminA, ['code' => 'ASSIGNED']);
        $unassigned = $this->project($this->companyA, $this->adminA, ['code' => 'HIDDEN']);
        $this->attach($assigned, $this->supervisorA, 'supervisor');
        $this->attach($assigned, $this->clientA, 'client');

        foreach ([$this->supervisorA, $this->clientA] as $user) {
            $token = $this->token($user);
            $this->withToken($token)->getJson('/api/v1/projects')->assertOk()
                ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $assigned->id);
            $this->withToken($token)->getJson("/api/v1/projects/{$assigned->id}")->assertOk();
            $this->withToken($token)->getJson("/api/v1/projects/{$unassigned->id}")->assertNotFound();
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_client_cannot_create_update_delete_status_or_progress(): void
    {
        $project = $this->project($this->companyA, $this->adminA);
        $this->attach($project, $this->clientA, 'client');
        $token = $this->token($this->clientA);
        $this->withToken($token)->postJson('/api/v1/projects', $this->payload())->assertForbidden();
        $this->withToken($token)->patchJson("/api/v1/projects/{$project->id}", ['name' => 'No'])->assertForbidden();
        $this->withToken($token)->deleteJson("/api/v1/projects/{$project->id}")->assertForbidden();
        $this->withToken($token)->patchJson("/api/v1/projects/{$project->id}/status", ['status' => 'active'])->assertForbidden();
        $this->withToken($token)->patchJson("/api/v1/projects/{$project->id}/progress", ['progress_percentage' => 20])->assertForbidden();
    }

    public function test_admin_updates_own_project_and_cross_company_mutations_are_not_found(): void
    {
        $project = $this->project($this->companyA, $this->adminA);
        $foreign = $this->project($this->companyB, $this->adminB);
        $token = $this->token($this->adminA);
        $this->withToken($token)->patchJson("/api/v1/projects/{$project->id}", ['name' => 'Updated', 'budget' => 2000])
            ->assertOk()->assertJsonPath('data.name', 'Updated');
        $this->withToken($token)->getJson("/api/v1/projects/{$foreign->id}")->assertNotFound();
        $this->withToken($token)->patchJson("/api/v1/projects/{$foreign->id}", ['name' => 'No'])->assertNotFound();
        $this->assertSame('Project', $foreign->fresh()->name);
    }

    public function test_status_transition_policy_and_status_validation_work(): void
    {
        $project = $this->project($this->companyA, $this->adminA);
        $token = $this->token($this->adminA);
        $this->withToken($token)->patchJson("/api/v1/projects/{$project->id}/status", ['status' => 'unknown'])
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->withToken($token)->patchJson("/api/v1/projects/{$project->id}/status", ['status' => 'completed'])
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->withToken($token)->patchJson("/api/v1/projects/{$project->id}/status", ['status' => 'active'])
            ->assertOk()->assertJsonPath('data.status', 'active');
        $this->withToken($token)->patchJson("/api/v1/projects/{$project->id}/status", ['status' => 'completed'])
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->withToken($token)->patchJson("/api/v1/projects/{$project->id}/status", ['status' => 'active'])
            ->assertUnprocessable();
        $this->assertSame(0, $project->fresh()->progress_percentage);
        $this->assertNull($project->actual_end_date);
    }

    public function test_progress_is_validated_and_requires_permission_plus_assignment(): void
    {
        $project = $this->project($this->companyA, $this->adminA);
        $token = $this->token($this->adminA);
        $this->withToken($token)->patchJson("/api/v1/projects/{$project->id}/progress", ['progress_percentage' => -1])
            ->assertUnprocessable();
        $this->withToken($token)->patchJson("/api/v1/projects/{$project->id}/progress", ['progress_percentage' => 101])
            ->assertUnprocessable();
        $this->withToken($token)->patchJson("/api/v1/projects/{$project->id}/progress", ['progress_percentage' => 45])
            ->assertOk()->assertJsonPath('data.progress_percentage', 45);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->supervisorA))->patchJson(
            "/api/v1/projects/{$project->id}/progress", ['progress_percentage' => 50],
        )->assertNotFound();
        $this->attach($project, $this->supervisorA, 'supervisor');
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->supervisorA))->patchJson(
            "/api/v1/projects/{$project->id}/progress", ['progress_percentage' => 50],
        )->assertForbidden();
    }

    public function test_admin_atomically_assigns_eligible_supervisor_client_and_member_without_role_changes(): void
    {
        $project = $this->project($this->companyA, $this->adminA);
        $beforeRoles = DB::table('model_has_roles')->count();
        $response = $this->withToken($this->token($this->adminA))->putJson("/api/v1/projects/{$project->id}/team", [
            'members' => [
                ['user_id' => $this->supervisorA->id, 'assignment_type' => 'supervisor'],
                ['user_id' => $this->clientA->id, 'assignment_type' => 'client'],
                ['user_id' => $this->adminA->id, 'assignment_type' => 'member'],
            ],
        ])->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame($beforeRoles, DB::table('model_has_roles')->count());
        $this->assertDatabaseHas('project_user', ['project_id' => $project->id, 'user_id' => $this->supervisorA->id, 'company_id' => $this->companyA->id]);
        $this->withToken($this->token($this->adminA))->getJson("/api/v1/projects/{$project->id}/team")
            ->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_invalid_team_payload_preserves_existing_assignments(): void
    {
        $project = $this->project($this->companyA, $this->adminA);
        $this->attach($project, $this->supervisorA, 'supervisor');
        $foreign = User::factory()->for($this->companyB)->create(['status' => 'Active']);
        $this->grantRole($foreign, 'site_supervisor');
        $token = $this->token($this->adminA);

        $this->withToken($token)->putJson("/api/v1/projects/{$project->id}/team", ['members' => [
            ['user_id' => $foreign->id, 'assignment_type' => 'supervisor'],
        ]])->assertUnprocessable();
        $this->assertDatabaseHas('project_user', ['project_id' => $project->id, 'user_id' => $this->supervisorA->id]);

        $this->withToken($token)->putJson("/api/v1/projects/{$project->id}/team", ['members' => [
            ['user_id' => $this->supervisorA->id, 'assignment_type' => 'supervisor'],
            ['user_id' => $this->supervisorA->id, 'assignment_type' => 'member'],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['members.1.user_id']);
        $this->assertSame(1, DB::table('project_user')->where('project_id', $project->id)->count());
    }

    public function test_inactive_suspended_soft_deleted_and_role_ineligible_users_are_rejected(): void
    {
        $project = $this->project($this->companyA, $this->adminA);
        $inactive = User::factory()->for($this->companyA)->create(['status' => 'Inactive']);
        $suspended = User::factory()->for($this->companyA)->create(['status' => 'Suspended']);
        $deleted = User::factory()->for($this->companyA)->create(['status' => 'Active']);
        foreach ([$inactive, $suspended, $deleted] as $user) {
            $this->grantRole($user, 'site_supervisor');
        }
        $deleted->delete();
        $token = $this->token($this->adminA);

        foreach ([$inactive, $suspended, $deleted] as $user) {
            $this->withToken($token)->putJson("/api/v1/projects/{$project->id}/team", ['members' => [
                ['user_id' => $user->id, 'assignment_type' => 'supervisor'],
            ]])->assertUnprocessable();
        }
        $this->withToken($token)->putJson("/api/v1/projects/{$project->id}/team", ['members' => [
            ['user_id' => $this->clientA->id, 'assignment_type' => 'supervisor'],
        ]])->assertUnprocessable();
    }

    public function test_client_cannot_manage_team_and_overview_hides_budget_but_admin_sees_it(): void
    {
        $project = $this->project($this->companyA, $this->adminA, ['budget' => 98765.43]);
        $this->attach($project, $this->clientA, 'client');
        $clientToken = $this->token($this->clientA);
        $this->withToken($clientToken)->getJson("/api/v1/projects/{$project->id}/team")->assertForbidden();
        $this->withToken($clientToken)->putJson("/api/v1/projects/{$project->id}/team", ['members' => []])->assertForbidden();
        $this->withToken($clientToken)->getJson("/api/v1/projects/{$project->id}/overview")
            ->assertOk()->assertJsonMissingPath('data.budget')->assertJsonMissingPath('data.currency');
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->adminA))->getJson("/api/v1/projects/{$project->id}/overview")
            ->assertOk()->assertJsonPath('data.budget', '98765.43')->assertJsonPath('data.currency', 'PKR');
    }

    public function test_project_deletion_is_soft_and_assignments_and_history_are_preserved(): void
    {
        $project = $this->project($this->companyA, $this->adminA);
        $this->attach($project, $this->supervisorA, 'supervisor');
        $token = $this->token($this->adminA);
        $this->withToken($token)->deleteJson("/api/v1/projects/{$project->id}")->assertOk();
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('project_user', ['project_id' => $project->id, 'user_id' => $this->supervisorA->id]);
        $this->withToken($token)->getJson("/api/v1/projects/{$project->id}")->assertNotFound();
    }

    public function test_activity_logs_are_tenant_scoped_and_secret_free(): void
    {
        $project = $this->project($this->companyA, $this->adminA);
        $this->withToken($this->token($this->adminA))->patchJson(
            "/api/v1/projects/{$project->id}/progress", ['progress_percentage' => 25],
        )->assertOk();
        $log = DB::table('activity_log')->latest('id')->first();
        $this->assertSame('project.progress_changed', $log->event);
        $this->assertStringContainsString('company_id', $log->properties);
        $this->assertStringNotContainsString('password', strtolower($log->properties));
        $this->assertStringNotContainsString('token', strtolower($log->properties));
    }

    public function test_tenant_context_does_not_leak_between_project_requests(): void
    {
        $projectA = $this->project($this->companyA, $this->adminA);
        $projectB = $this->project($this->companyB, $this->adminB);
        $this->withToken($this->token($this->adminA))->getJson('/api/v1/projects')
            ->assertOk()->assertJsonPath('data.0.id', $projectA->id);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->adminB))->getJson('/api/v1/projects')
            ->assertOk()->assertJsonPath('data.0.id', $projectB->id)->assertJsonMissing(['id' => $projectA->id]);
    }

    private function payload(): array
    {
        return [
            'name' => 'Project', 'code' => 'PRJ-001', 'description' => 'Test project',
            'start_date' => '2026-10-18', 'expected_end_date' => '2027-01-18', 'budget' => 1000,
        ];
    }

    private function project(Company $company, User $creator, array $attributes = []): Project
    {
        return Project::factory()->for($company)->create(array_merge([
            'name' => 'Project', 'created_by' => $creator->id, 'currency' => $company->currency,
        ], $attributes));
    }

    private function attach(Project $project, User $user, string $type): void
    {
        $project->members()->attach($user->id, [
            'company_id' => $project->company_id, 'assignment_type' => $type,
            'assigned_by' => $project->created_by, 'assigned_at' => now(),
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('project-management-test')->plainTextToken;
    }
}
