<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\DailySiteReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailySiteReportsTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $adminA;

    private User $reviewerA;

    private User $adminB;

    private User $supervisorA;

    private User $otherSupervisorA;

    private User $clientA;

    private Project $projectA;

    private Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->companyA = Company::factory()->create();
        $this->companyB = Company::factory()->create();
        $this->adminA = $this->user($this->companyA, 'company_admin');
        $this->reviewerA = $this->user($this->companyA, 'company_admin');
        $this->adminB = $this->user($this->companyB, 'company_admin');
        $this->supervisorA = $this->user($this->companyA, 'site_supervisor');
        $this->otherSupervisorA = $this->user($this->companyA, 'site_supervisor');
        $this->clientA = $this->user($this->companyA, 'client');
        $this->projectA = $this->project($this->companyA, $this->adminA, 'A-001');
        $this->projectB = $this->project($this->companyB, $this->adminB, 'B-001');
        $this->attach($this->projectA, $this->supervisorA, 'supervisor');
        $this->attach($this->projectA, $this->clientA, 'client');
    }

    public function test_admin_and_assigned_supervisor_create_drafts_with_server_ownership_and_totals(): void
    {
        $response = $this->withToken($this->token($this->adminA))->postJson($this->collectionUrl(), $this->payload('2026-10-19'))
            ->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.is_client_visible', false)
            ->assertJsonPath('data.total_workers_present', 23);
        $this->assertDatabaseHas('daily_site_reports', [
            'id' => $response->json('data.id'), 'company_id' => $this->companyA->id,
            'project_id' => $this->projectA->id, 'created_by' => $this->adminA->id,
        ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->supervisorA))->postJson($this->collectionUrl(), $this->payload('2026-10-20'))
            ->assertCreated()->assertJsonPath('data.total_workers_present', 23);
    }

    public function test_unassigned_supervisor_client_and_cross_company_project_cannot_create(): void
    {
        $this->withToken($this->token($this->otherSupervisorA))->postJson($this->collectionUrl(), $this->payload())->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->clientA))->postJson($this->collectionUrl(), $this->payload())->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->adminA))->postJson(
            "/api/v1/projects/{$this->projectB->id}/daily-reports", $this->payload(),
        )->assertNotFound();
    }

    public function test_creation_rejects_protected_fields_and_conflicting_total(): void
    {
        $this->withToken($this->token($this->adminA))->postJson($this->collectionUrl(), array_merge($this->payload(), [
            'company_id' => $this->companyB->id, 'project_id' => $this->projectB->id,
            'created_by' => $this->adminB->id, 'status' => 'approved', 'is_client_visible' => true,
            'total_workers_present' => 999, 'reviewed_by' => $this->adminA->id,
            'reviewed_at' => now()->toIso8601String(), 'review_notes' => 'Injected',
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'company_id', 'project_id', 'created_by', 'status', 'is_client_visible',
            'total_workers_present', 'reviewed_by', 'reviewed_at', 'review_notes',
        ]);
    }

    public function test_workforce_progress_and_active_date_validation_are_enforced(): void
    {
        $token = $this->token($this->adminA);
        $this->withToken($token)->postJson($this->collectionUrl(), array_merge($this->payload(), [
            'workers' => [['worker_type' => 'mason', 'workers_present' => -1]],
            'progress_percentage' => 101,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['workers.0.workers_present', 'progress_percentage']);
        $this->withToken($token)->postJson($this->collectionUrl(), array_merge($this->payload(), ['workers' => [
            ['worker_type' => 'mason', 'workers_present' => 1], ['worker_type' => 'mason', 'workers_present' => 2],
        ]]))->assertUnprocessable()->assertJsonValidationErrors(['workers.1.worker_type']);
        $this->withToken($token)->postJson($this->collectionUrl(), $this->payload('2026-10-19'))->assertCreated();
        $this->withToken($token)->postJson($this->collectionUrl(), $this->payload('2026-10-19'))
            ->assertUnprocessable()->assertJsonValidationErrors(['report_date']);
    }

    public function test_admin_and_assigned_supervisor_list_reports_but_unassigned_supervisor_gets_not_found(): void
    {
        $report = $this->report($this->projectA, $this->supervisorA, ['report_date' => '2026-10-19']);
        $this->report($this->projectB, $this->adminB, ['report_date' => '2026-10-19']);
        foreach ([$this->adminA, $this->supervisorA] as $user) {
            $this->withToken($this->token($user))->getJson($this->collectionUrl().'?date=2026-10-19&search=Daily')
                ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $report->id);
            $this->app['auth']->forgetGuards();
        }
        $this->withToken($this->token($this->otherSupervisorA))->getJson($this->collectionUrl())->assertNotFound();
    }

    public function test_client_list_exposes_only_approved_visible_reports_without_hidden_counts(): void
    {
        $this->report($this->projectA, $this->supervisorA, ['report_date' => '2026-10-16', 'status' => 'draft']);
        $this->report($this->projectA, $this->supervisorA, ['report_date' => '2026-10-17', 'status' => 'submitted']);
        $this->report($this->projectA, $this->supervisorA, ['report_date' => '2026-10-18', 'status' => 'changes_required']);
        $this->report($this->projectA, $this->supervisorA, ['report_date' => '2026-10-19', 'status' => 'approved', 'is_client_visible' => false]);
        $visible = $this->report($this->projectA, $this->supervisorA, ['report_date' => '2026-10-20', 'status' => 'approved', 'is_client_visible' => true]);

        $this->withToken($this->token($this->clientA))->getJson($this->collectionUrl())
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonMissingPath('data.0.review_notes');
    }

    public function test_report_details_enforce_company_project_and_client_visibility(): void
    {
        $report = $this->report($this->projectA, $this->supervisorA);
        $foreign = $this->report($this->projectB, $this->adminB);
        $this->withToken($this->token($this->adminA))->getJson($this->reportUrl($report))->assertOk();
        $this->withToken($this->token($this->adminA))->getJson($this->reportUrl($foreign))->assertNotFound();
        $this->withToken($this->token($this->adminA))->getJson(
            "/api/v1/projects/{$this->projectB->id}/daily-reports/{$report->id}",
        )->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->clientA))->getJson($this->reportUrl($report))->assertNotFound();
        $this->setReportState($report, ['status' => 'approved', 'is_client_visible' => true, 'review_notes' => 'Internal']);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->clientA))->getJson($this->reportUrl($report))
            ->assertOk()->assertJsonMissingPath('data.review_notes');
    }

    public function test_supervisor_edits_only_own_draft_or_changes_required_report(): void
    {
        $own = $this->report($this->projectA, $this->supervisorA);
        $other = $this->report($this->projectA, $this->otherSupervisorA, ['report_date' => '2026-10-20']);
        $token = $this->token($this->supervisorA);
        $this->withToken($token)->patchJson($this->reportUrl($own), ['title' => 'Updated'])->assertOk();
        $this->withToken($token)->patchJson($this->reportUrl($other), ['title' => 'No'])->assertForbidden();
        $this->setReportState($own, ['status' => 'changes_required']);
        $this->withToken($token)->patchJson($this->reportUrl($own), ['title' => 'Corrected'])->assertOk();
        foreach (['submitted', 'approved'] as $status) {
            $this->setReportState($own, ['status' => $status]);
            $this->withToken($token)->patchJson($this->reportUrl($own), ['title' => 'Locked'])->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->clientA))->patchJson($this->reportUrl($own), ['title' => 'No'])->assertNotFound();
    }

    public function test_workforce_update_recalculates_total_and_invalid_update_is_atomic(): void
    {
        $report = $this->report($this->projectA, $this->supervisorA);
        $this->addWorker($report, 'mason', 2);
        $report->update(['total_workers_present' => 2]);
        $token = $this->token($this->supervisorA);
        $this->withToken($token)->patchJson($this->reportUrl($report), ['workers' => [
            ['worker_type' => 'helper', 'workers_present' => 5], ['worker_type' => 'electrician', 'workers_present' => 3],
        ]])->assertOk()->assertJsonPath('data.total_workers_present', 8);
        $this->withToken($token)->patchJson($this->reportUrl($report), ['title' => 'Should not save', 'workers' => [
            ['worker_type' => 'helper', 'workers_present' => -1],
        ]])->assertUnprocessable();
        $this->assertNotSame('Should not save', $report->fresh()->title);
        $this->assertSame(8, $report->fresh()->total_workers_present);
        $this->assertDatabaseCount('daily_site_report_workers', 2);
    }

    public function test_draft_and_changes_required_can_submit_but_other_states_cannot(): void
    {
        $report = $this->report($this->projectA, $this->supervisorA);
        $token = $this->token($this->supervisorA);
        $this->withToken($token)->postJson($this->reportUrl($report).'/submit')->assertOk()
            ->assertJsonPath('data.status', 'submitted');
        $this->withToken($token)->postJson($this->reportUrl($report).'/submit')->assertUnprocessable();
        $this->setReportState($report, ['status' => 'changes_required', 'review_notes' => 'Fix']);
        $this->withToken($token)->postJson($this->reportUrl($report).'/submit')->assertOk()
            ->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.review_notes', null);
        $this->setReportState($report, ['status' => 'approved']);
        $this->withToken($token)->postJson($this->reportUrl($report).'/submit')->assertUnprocessable();
    }

    public function test_reviewer_can_approve_or_request_changes_and_notes_are_required(): void
    {
        $approve = $this->report($this->projectA, $this->supervisorA, ['status' => 'submitted']);
        $changes = $this->report($this->projectA, $this->supervisorA, ['report_date' => '2026-10-20', 'status' => 'submitted']);
        $token = $this->token($this->reviewerA);
        $this->withToken($token)->postJson($this->reportUrl($approve).'/review', ['action' => 'approve'])
            ->assertOk()->assertJsonPath('data.status', 'approved');
        $this->withToken($token)->postJson($this->reportUrl($changes).'/review', ['action' => 'request_changes'])
            ->assertUnprocessable()->assertJsonValidationErrors(['review_notes']);
        $this->withToken($token)->postJson($this->reportUrl($changes).'/review', [
            'action' => 'request_changes', 'review_notes' => 'Correct workforce.',
        ])->assertOk()->assertJsonPath('data.status', 'changes_required');
    }

    public function test_supervisor_client_and_creator_cannot_review(): void
    {
        $report = $this->report($this->projectA, $this->supervisorA, ['status' => 'submitted']);
        foreach ([$this->supervisorA, $this->clientA] as $user) {
            $this->withToken($this->token($user))->postJson($this->reportUrl($report).'/review', ['action' => 'approve'])
                ->assertStatus($user->is($this->clientA) ? 404 : 403);
            $this->app['auth']->forgetGuards();
        }
        $own = $this->report($this->projectA, $this->adminA, ['report_date' => '2026-10-20', 'status' => 'submitted']);
        $this->withToken($this->token($this->adminA))->postJson($this->reportUrl($own).'/review', ['action' => 'approve'])
            ->assertForbidden();
    }

    public function test_only_admin_can_control_visibility_and_only_approved_can_be_published(): void
    {
        $report = $this->report($this->projectA, $this->supervisorA, ['status' => 'submitted']);
        $token = $this->token($this->reviewerA);
        $this->withToken($token)->patchJson($this->reportUrl($report).'/visibility', ['is_client_visible' => true])
            ->assertUnprocessable();
        $this->setReportState($report, ['status' => 'approved']);
        $this->withToken($token)->patchJson($this->reportUrl($report).'/visibility', ['is_client_visible' => true])
            ->assertOk()->assertJsonPath('data.is_client_visible', true);
        $this->withToken($token)->patchJson($this->reportUrl($report).'/visibility', ['is_client_visible' => false])
            ->assertOk()->assertJsonPath('data.is_client_visible', false);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->clientA))->patchJson($this->reportUrl($report).'/visibility', ['is_client_visible' => true])
            ->assertNotFound();
    }

    public function test_admin_soft_deletes_report_while_workforce_and_history_remain(): void
    {
        $report = $this->report($this->projectA, $this->supervisorA);
        $this->addWorker($report, 'mason', 2);
        $this->withToken($this->token($this->adminA))->deleteJson($this->reportUrl($report))->assertOk();
        $this->assertSoftDeleted('daily_site_reports', ['id' => $report->id]);
        $this->assertDatabaseHas('daily_site_report_workers', ['daily_site_report_id' => $report->id]);
        $this->withToken($this->token($this->adminA))->getJson($this->reportUrl($report))->assertNotFound();
        $this->app['auth']->forgetGuards();
        $new = $this->report($this->projectA, $this->supervisorA, ['report_date' => $report->report_date]);
        $this->withToken($this->token($this->supervisorA))->deleteJson($this->reportUrl($new))->assertForbidden();
    }

    public function test_activity_logs_workflow_without_secrets_and_tenant_context_does_not_leak(): void
    {
        $report = $this->report($this->projectA, $this->supervisorA);
        $this->withToken($this->token($this->supervisorA))->postJson($this->reportUrl($report).'/submit')->assertOk();
        $log = DB::table('activity_log')->latest('id')->first();
        $this->assertSame('site_report.submitted', $log->event);
        $this->assertStringContainsString('company_id', $log->properties);
        $this->assertStringNotContainsString('password', strtolower($log->properties));
        $this->assertStringNotContainsString('token', strtolower($log->properties));
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->adminB))->getJson($this->collectionUrl())->assertNotFound();
    }

    private function user(Company $company, string $role): User
    {
        $user = User::factory()->for($company)->create(['status' => 'Active']);
        $this->grantRole($user, $role);

        return $user;
    }

    private function project(Company $company, User $creator, string $code): Project
    {
        return Project::factory()->for($company)->create([
            'created_by' => $creator->id, 'currency' => $company->currency, 'code' => $code,
        ]);
    }

    private function attach(Project $project, User $user, string $type): void
    {
        $project->members()->attach($user->id, ['company_id' => $project->company_id,
            'assignment_type' => $type, 'assigned_by' => $project->created_by, 'assigned_at' => now()]);
    }

    private function report(Project $project, User $creator, array $attributes = []): DailySiteReport
    {
        return DailySiteReport::factory()->create(array_merge([
            'company_id' => $project->company_id, 'project_id' => $project->id,
            'created_by' => $creator->id, 'report_date' => '2026-10-19', 'title' => 'Daily Report',
        ], $attributes));
    }

    private function payload(string $date = '2026-10-19'): array
    {
        return ['report_date' => $date, 'title' => 'Foundation Work', 'work_completed' => 'Reinforcement completed.',
            'progress_percentage' => 35, 'workers' => [
                ['worker_type' => 'mason', 'workers_present' => 8],
                ['worker_type' => 'helper', 'workers_present' => 12],
                ['worker_type' => 'electrician', 'workers_present' => 3],
            ]];
    }

    private function setReportState(DailySiteReport $report, array $attributes): void
    {
        $report->forceFill($attributes)->save();
    }

    private function addWorker(DailySiteReport $report, string $type, int $count): void
    {
        $worker = $report->workers()->make(['worker_type' => $type, 'workers_present' => $count]);
        $worker->company_id = $report->company_id;
        $worker->save();
    }

    private function collectionUrl(): string
    {
        return "/api/v1/projects/{$this->projectA->id}/daily-reports";
    }

    private function reportUrl(DailySiteReport $report): string
    {
        return $this->collectionUrl()."/{$report->id}";
    }

    private function token(User $user): string
    {
        return $user->createToken('daily-report-test')->plainTextToken;
    }
}
