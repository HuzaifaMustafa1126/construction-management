<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListProjectsRequest;
use App\Http\Requests\Api\V1\StoreProjectRequest;
use App\Http\Requests\Api\V1\UpdateProjectProgressRequest;
use App\Http\Requests\Api\V1\UpdateProjectRequest;
use App\Http\Requests\Api\V1\UpdateProjectStatusRequest;
use App\Http\Resources\Api\V1\ProjectOverviewResource;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    private const TRANSITIONS = [
        'planning' => ['active', 'cancelled'],
        'active' => ['on_hold', 'completed', 'cancelled'],
        'on_hold' => ['active', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(private readonly ProjectService $projects) {}

    public function index(ListProjectsRequest $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->can('projects.view')) {
            return $this->forbidden();
        }
        $validated = $request->validated();
        $query = $this->listQuery($request, $tenant);
        if ($search = $validated['search'] ?? null) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
        }
        if ($status = $validated['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($managerId = $validated['project_manager_id'] ?? null) {
            $query->whereHas('members', fn ($members) => $members->where('users.id', $managerId)
                ->whereHas('roles', fn ($roles) => $roles->where('name', 'project_manager')));
        }
        if ($from = $validated['date_from'] ?? null) {
            $query->whereDate('start_date', '>=', $from);
        }
        if ($to = $validated['date_to'] ?? null) {
            $query->whereDate('start_date', '<=', $to);
        }
        $page = $query->orderBy($validated['sort'] ?? 'created_at', $validated['direction'] ?? 'desc')
            ->paginate($validated['per_page'] ?? 15)->withQueryString();

        return response()->json(['data' => ProjectResource::collection($page->items()), 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
            'project_managers' => $this->visibleManagers($request, $tenant),
        ]]);
    }

    public function managers(Request $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->can('projects.view')) {
            return $this->forbidden();
        }

        return response()->json(['data' => $this->visibleManagers($request, $tenant)]);
    }

    public function archived(ListProjectsRequest $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->can('projects.restore')) {
            return $this->forbidden();
        }

        $validated = $request->validated();
        $query = Project::onlyTrashed()->forCompany($tenant->companyId())
            ->with(['members.roles:id,name', 'client:id,name,company_name']);
        if ($search = $validated['search'] ?? null) {
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }
        if ($status = $validated['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($managerId = $validated['project_manager_id'] ?? null) {
            $query->whereHas('members', fn ($members) => $members->where('users.id', $managerId)
                ->whereHas('roles', fn ($roles) => $roles->where('name', 'project_manager')));
        }
        $page = $query->orderBy($validated['sort'] ?? 'created_at', $validated['direction'] ?? 'desc')
            ->paginate($validated['per_page'] ?? 15)->withQueryString();

        return response()->json(['data' => ProjectResource::collection($page->items()), 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
            'project_managers' => $this->visibleManagers($request, $tenant),
        ]]);
    }

    public function store(StoreProjectRequest $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->can('projects.create')) {
            return $this->forbidden();
        }
        $project = $this->projects->create(
            $request->validated(), $tenant->companyId(), $tenant->company()->currency ?: 'PKR', $request->user(),
        );
        $this->log($request, $project, 'project.created', ['after' => $this->safeChanges($project)]);

        return response()->json(['data' => new ProjectResource($project->load(['members.roles:id,name', 'client:id,name,company_name'])), 'message' => 'Project created successfully.'], 201);
    }

    public function show(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $request->user()->can('projects.view')) {
            return $this->forbidden();
        }
        $project = $this->viewable($request, $tenant, $id);

        return $project ? response()->json(['data' => new ProjectResource($project->load(['members.roles:id,name', 'client:id,name,company_name']))]) : $this->notFound();
    }

    public function update(UpdateProjectRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->viewable($request, $tenant, $id);
        if (! $project) {
            return $this->notFound();
        }
        if (! $request->user()->can('projects.edit') || ! $request->user()->can('update', $project)) {
            return $this->forbidden();
        }
        $validated = $request->validated();
        $before = $project->only(array_keys($validated));
        $project->fill($validated);
        $project->updated_by = $request->user()->id;
        $project->save();
        $this->log($request, $project, 'project.updated', ['before' => $before, 'after' => $project->only(array_keys($validated))]);

        return response()->json(['data' => new ProjectResource($project->load(['members.roles:id,name', 'client:id,name,company_name'])), 'message' => 'Project updated successfully.']);
    }

    public function destroy(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->viewable($request, $tenant, $id);
        if (! $project) {
            return $this->notFound();
        }
        if (! $request->user()->can('projects.delete') || ! $request->user()->can('delete', $project)) {
            return $this->forbidden();
        }
        $this->log($request, $project, 'project.deleted', ['team_assignments_preserved' => $project->members()->count()]);
        $project->delete();

        return response()->json(['message' => 'Project archived successfully.']);
    }

    public function restore(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = Project::onlyTrashed()->forCompany($tenant->companyId())->find($id);
        if (! $project) {
            return $this->notFound();
        }
        if (! $request->user()->can('restore', $project)) {
            return $this->forbidden();
        }
        if (Project::query()->forCompany($tenant->companyId())->whereRaw('lower(code) = ?', [mb_strtolower($project->code)])->exists()) {
            return response()->json([
                'message' => 'The project cannot be restored because its code is already in use.',
                'errors' => ['code' => ['Change the active project code before restoring this project.']],
            ], 422);
        }

        $project->restore();
        $this->log($request, $project, 'project.restored', ['restored_at' => now()->toIso8601String()]);

        return response()->json([
            'data' => new ProjectResource($project->load(['members.roles:id,name', 'client:id,name,company_name'])),
            'message' => 'Project restored successfully.',
        ]);
    }

    public function status(UpdateProjectStatusRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->viewable($request, $tenant, $id);
        if (! $project) {
            return $this->notFound();
        }
        if (! $request->user()->can('projects.edit') || ! $request->user()->can('update', $project)) {
            return $this->forbidden();
        }
        $status = $request->validated('status');
        if ($status !== $project->status && ! in_array($status, self::TRANSITIONS[$project->status] ?? [], true)) {
            return response()->json(['message' => 'The requested project status transition is not allowed.', 'errors' => [
                'status' => ['The requested project status transition is not allowed.'],
            ]], 422);
        }
        $before = $project->status;
        $project->status = $status;
        $project->updated_by = $request->user()->id;
        $project->save();
        $this->log($request, $project, 'project.status_changed', ['before' => $before, 'after' => $status]);

        return response()->json(['data' => new ProjectResource($project->load(['members.roles:id,name', 'client:id,name,company_name'])), 'message' => 'Project status updated successfully.']);
    }

    public function progress(UpdateProjectProgressRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->viewable($request, $tenant, $id);
        if (! $project) {
            return $this->notFound();
        }
        if (! $request->user()->can('projects.edit') || ! $request->user()->can('update', $project)) {
            return $this->forbidden();
        }
        $before = $project->progress_percentage;
        $progress = $request->validated('progress_percentage');
        $project->progress_percentage = $progress;
        $project->updated_by = $request->user()->id;
        $project->save();
        $this->log($request, $project, 'project.progress_changed', ['before' => $before, 'after' => $progress]);

        return response()->json(['data' => new ProjectResource($project->load(['members.roles:id,name', 'client:id,name,company_name'])), 'message' => 'Project progress updated successfully.']);
    }

    public function overview(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $request->user()->can('projects.view')) {
            return $this->forbidden();
        }
        $project = $this->viewable($request, $tenant, $id);

        return $project ? response()->json(['data' => new ProjectOverviewResource($project->load(['members:id,name', 'client:id,name,company_name']))]) : $this->notFound();
    }

    private function viewable(Request $request, TenantContext $tenant, int $id): ?Project
    {
        $project = Project::query()->forCompany($tenant->companyId())->find($id);

        return $project && $request->user()->can('view', $project) ? $project : null;
    }

    private function listQuery(Request $request, TenantContext $tenant): Builder
    {
        return Project::query()->forCompany($tenant->companyId())->accessibleTo($request->user())
            ->with(['members.roles:id,name', 'client:id,name,company_name']);
    }

    private function visibleManagers(Request $request, TenantContext $tenant)
    {
        return User::query()->where('company_id', $tenant->companyId())->where('status', User::STATUS_ACTIVE)
            ->whereHas('roles', fn ($roles) => $roles->where('name', 'project_manager'))
            ->whereHas('projects', fn ($projects) => $projects->accessibleTo($request->user()))
            ->orderBy('name')->get(['id', 'name']);
    }

    private function safeChanges(Project $project): array
    {
        return $project->only(['name', 'code', 'client_id', 'description', 'location', 'start_date', 'expected_end_date', 'actual_end_date', 'budget', 'currency', 'progress_percentage', 'status']);
    }

    private function log(Request $request, Project $project, string $event, array $changes): void
    {
        activity('projects')->causedBy($request->user())->performedOn($project)->event($event)
            ->withProperties(['company_id' => $project->company_id, 'project_id' => $project->id, 'changes' => $changes])->log($event);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Project not found.'], 404);
    }
}
