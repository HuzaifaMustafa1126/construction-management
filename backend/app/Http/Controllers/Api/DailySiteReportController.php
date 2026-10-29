<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListDailySiteReportsRequest;
use App\Http\Requests\Api\V1\ReviewDailySiteReportRequest;
use App\Http\Requests\Api\V1\StoreDailySiteReportRequest;
use App\Http\Requests\Api\V1\SubmitDailySiteReportRequest;
use App\Http\Requests\Api\V1\UpdateDailySiteReportRequest;
use App\Http\Requests\Api\V1\UpdateDailySiteReportVisibilityRequest;
use App\Http\Resources\Api\V1\DailySiteReportResource;
use App\Models\DailySiteReport;
use App\Models\Project;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DailySiteReportController extends Controller
{
    public function index(ListDailySiteReportsRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->project($request, $tenant, $id);
        if (! $project) {
            return $this->notFound();
        }
        if (! $request->user()->can('daily_reports.view')) {
            return $this->forbidden();
        }
        $validated = $request->validated();
        $query = DailySiteReport::query()->forCompany($tenant->companyId())->where('project_id', $project->id)
            ->with(['project:id,name,code', 'creator:id,name', 'workers']);
        if ($request->user()->hasRole('client')) {
            $query->where('status', 'approved')->where('is_client_visible', true);
        }
        if ($search = $validated['search'] ?? null) {
            $query->where('title', 'like', "%{$search}%");
        }
        if ($date = $validated['date'] ?? null) {
            $query->whereDate('report_date', $date);
        }
        if ($from = $validated['date_from'] ?? null) {
            $query->whereDate('report_date', '>=', $from);
        }
        if ($to = $validated['date_to'] ?? null) {
            $query->whereDate('report_date', '<=', $to);
        }
        if ($status = $validated['status'] ?? null) {
            $query->where('status', $status);
        }
        $page = $query->orderBy($validated['sort'] ?? 'report_date', $validated['direction'] ?? 'desc')
            ->paginate($validated['per_page'] ?? 15)->withQueryString();

        return response()->json(['data' => DailySiteReportResource::collection($page->items()), 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
        ]]);
    }

    public function store(StoreDailySiteReportRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->project($request, $tenant, $id);
        if (! $project) {
            return $this->notFound();
        }
        if (! $request->user()->can('daily_reports.create')
            || ! $request->user()->can('create', [DailySiteReport::class, $project])) {
            return $this->forbidden();
        }
        $validated = $request->validated();
        $workers = Arr::pull($validated, 'workers', []);
        $report = DB::transaction(function () use ($validated, $workers, $project, $tenant, $request): DailySiteReport {
            $report = new DailySiteReport($validated);
            $report->company_id = $tenant->companyId();
            $report->project_id = $project->id;
            $report->created_by = $request->user()->id;
            $report->status = 'draft';
            $report->is_client_visible = false;
            $report->total_workers_present = collect($workers)->sum('workers_present');
            $report->save();
            $this->replaceWorkers($report, $workers);
            $this->log($request, $report, 'site_report.created', ['after' => $this->safeFields($report)]);

            return $report;
        });

        return response()->json(['data' => new DailySiteReportResource($this->load($report)),
            'message' => 'Daily site report created successfully.'], 201);
    }

    public function show(Request $request, TenantContext $tenant, int $id, int $reportId): JsonResponse
    {
        if (! $request->user()->can('daily_reports.view')) {
            return $this->forbidden();
        }
        $report = $this->report($request, $tenant, $id, $reportId);

        return $report ? response()->json(['data' => new DailySiteReportResource($this->load($report))]) : $this->notFound();
    }

    public function update(UpdateDailySiteReportRequest $request, TenantContext $tenant, int $id, int $reportId): JsonResponse
    {
        $report = $this->report($request, $tenant, $id, $reportId);
        if (! $report) {
            return $this->notFound();
        }
        if (! $request->user()->can('site_reports.update') || ! $request->user()->can('update', $report)) {
            return $this->forbidden();
        }
        $validated = $request->validated();
        $hasWorkers = array_key_exists('workers', $validated);
        $workers = Arr::pull($validated, 'workers', []);
        $before = $report->only(array_keys($validated));
        DB::transaction(function () use ($report, $validated, $hasWorkers, $workers): void {
            $report->fill($validated);
            if ($hasWorkers) {
                $report->total_workers_present = collect($workers)->sum('workers_present');
            }
            $report->save();
            if ($hasWorkers) {
                $this->replaceWorkers($report, $workers);
            }
        });
        $this->log($request, $report, 'site_report.updated', [
            'before' => $before, 'after' => $report->only(array_keys($validated)),
            'workforce_replaced' => $hasWorkers,
        ]);

        return response()->json(['data' => new DailySiteReportResource($this->load($report)),
            'message' => 'Daily site report updated successfully.']);
    }

    public function submit(SubmitDailySiteReportRequest $request, TenantContext $tenant, int $id, int $reportId): JsonResponse
    {
        $report = $this->report($request, $tenant, $id, $reportId);
        if (! $report) {
            return $this->notFound();
        }
        if (! $request->user()->can('site_reports.update') || ! $request->user()->can('submit', $report)) {
            return $this->forbidden();
        }
        if (! in_array($report->status, ['draft', 'changes_required'], true)) {
            return $this->transitionError();
        }
        if (blank($report->title) || blank($report->work_completed)) {
            return response()->json(['message' => 'The report is incomplete.', 'errors' => [
                'report' => ['A title and completed-work summary are required before submission.'],
            ]], 422);
        }
        $before = $report->status;
        $report->status = 'submitted';
        $report->submitted_by = $request->user()->id;
        $report->submitted_at = now();
        $report->reviewed_by = null;
        $report->reviewed_at = null;
        $report->review_notes = null;
        $report->is_client_visible = false;
        $report->save();
        $this->log($request, $report, 'site_report.submitted', ['before' => $before, 'after' => 'submitted']);

        return response()->json(['data' => new DailySiteReportResource($this->load($report)), 'message' => 'Daily site report submitted successfully.']);
    }

    public function review(ReviewDailySiteReportRequest $request, TenantContext $tenant, int $id, int $reportId): JsonResponse
    {
        $report = $this->report($request, $tenant, $id, $reportId);
        if (! $report) {
            return $this->notFound();
        }
        if (! $request->user()->can('daily_reports.approve') || ! $request->user()->can('review', $report)) {
            return $this->forbidden();
        }
        if ($report->status !== 'submitted') {
            return $this->transitionError();
        }
        $validated = $request->validated();
        $approved = $validated['action'] === 'approve';
        $report->status = $approved ? 'approved' : 'changes_required';
        $report->reviewed_by = $request->user()->id;
        $report->reviewed_at = now();
        $report->review_notes = $validated['review_notes'] ?? null;
        $report->is_client_visible = $approved && ($validated['publish_to_clients'] ?? false);
        $report->save();
        $event = $approved ? 'site_report.approved' : 'site_report.changes_requested';
        $this->log($request, $report, $event, [
            'action' => $validated['action'],
            'review_notes' => $report->review_notes,
            'is_client_visible' => $report->is_client_visible,
        ]);

        return response()->json(['data' => new DailySiteReportResource($this->load($report)), 'message' => 'Daily site report reviewed successfully.']);
    }

    public function visibility(UpdateDailySiteReportVisibilityRequest $request, TenantContext $tenant, int $id, int $reportId): JsonResponse
    {
        $report = $this->report($request, $tenant, $id, $reportId);
        if (! $report) {
            return $this->notFound();
        }
        if (! $request->user()->can('daily_reports.approve') || ! $request->user()->can('visibility', $report)) {
            return $this->forbidden();
        }
        $visible = $request->validated('is_client_visible');
        if ($visible && $report->status !== 'approved') {
            return response()->json(['message' => 'Only approved reports can be made client-visible.', 'errors' => [
                'is_client_visible' => ['Only approved reports can be made client-visible.'],
            ]], 422);
        }
        $before = $report->is_client_visible;
        $report->is_client_visible = $visible;
        $report->save();
        $this->log($request, $report, 'site_report.visibility_changed', ['before' => $before, 'after' => $visible]);

        return response()->json(['data' => new DailySiteReportResource($this->load($report)), 'message' => 'Client visibility updated successfully.']);
    }

    public function destroy(Request $request, TenantContext $tenant, int $id, int $reportId): JsonResponse
    {
        $report = $this->report($request, $tenant, $id, $reportId);
        if (! $report) {
            return $this->notFound();
        }
        if (! $request->user()->can('site_reports.delete') || ! $request->user()->can('delete', $report)) {
            return $this->forbidden();
        }
        $this->log($request, $report, 'site_report.deleted', ['workers_preserved' => $report->workers()->count()]);
        $report->delete();

        return response()->json(['message' => 'Daily site report deleted successfully.']);
    }

    private function project(Request $request, TenantContext $tenant, int $id): ?Project
    {
        $project = Project::query()->forCompany($tenant->companyId())->find($id);

        return $project && $request->user()->can('view', $project) ? $project : null;
    }

    private function report(Request $request, TenantContext $tenant, int $projectId, int $reportId): ?DailySiteReport
    {
        $project = $this->project($request, $tenant, $projectId);
        if (! $project) {
            return null;
        }
        $report = DailySiteReport::query()->forCompany($tenant->companyId())
            ->where('project_id', $project->id)->find($reportId);

        return $report && $request->user()->can('view', $report) ? $report : null;
    }

    private function replaceWorkers(DailySiteReport $report, array $workers): void
    {
        $report->workers()->delete();
        foreach ($workers as $worker) {
            $record = $report->workers()->make($worker);
            $record->company_id = $report->company_id;
            $record->save();
        }
    }

    private function load(DailySiteReport $report): DailySiteReport
    {
        return $report->load(['project:id,name,code', 'creator:id,name', 'workers']);
    }

    private function safeFields(DailySiteReport $report): array
    {
        return $report->only(['report_date', 'title', 'work_completed', 'work_in_progress', 'issues', 'delays',
            'delay_reason', 'weather_conditions', 'general_notes', 'total_workers_present', 'progress_percentage', 'status']);
    }

    private function log(Request $request, DailySiteReport $report, string $event, array $changes): void
    {
        activity('site_reports')->causedBy($request->user())->performedOn($report)->event($event)
            ->withProperties(['company_id' => $report->company_id, 'project_id' => $report->project_id,
                'report_id' => $report->id, 'changes' => $changes])->log($event);
    }

    private function transitionError(): JsonResponse
    {
        return response()->json(['message' => 'The requested report workflow transition is not allowed.', 'errors' => [
            'status' => ['The requested report workflow transition is not allowed.'],
        ]], 422);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Daily site report not found.'], 404);
    }
}
