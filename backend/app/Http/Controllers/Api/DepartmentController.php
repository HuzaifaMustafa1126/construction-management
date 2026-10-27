<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListDepartmentsRequest;
use App\Http\Requests\Api\V1\StoreDepartmentRequest;
use App\Http\Requests\Api\V1\UpdateDepartmentRequest;
use App\Http\Requests\Api\V1\UpdateDepartmentStatusRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Department;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(ListDepartmentsRequest $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->can('departments.view')) {
            return $this->forbidden();
        }
        $validated = $request->validated();
        $query = Department::query()->forCompany($tenant->companyId())->withCount('users');
        if ($search = $validated['search'] ?? null) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
        }
        if ($status = $validated['status'] ?? null) {
            $query->where('status', $status);
        }
        $page = $query->orderBy($validated['sort'] ?? 'name', $validated['direction'] ?? 'asc')
            ->paginate($validated['per_page'] ?? 15)->withQueryString();

        return response()->json(['data' => collect($page->items())->map(fn ($d) => $this->serialize($d)), 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
        ]]);
    }

    public function store(StoreDepartmentRequest $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->can('departments.create')) {
            return $this->forbidden();
        }
        $department = new Department($request->validated());
        $department->company_id = $tenant->companyId();
        $department->created_by = $request->user()->id;
        $department->save();
        $this->log($request, $tenant, $department, 'department.created', ['after' => $department->only(['name', 'code', 'description', 'status'])]);

        return response()->json(['data' => $this->serialize($department), 'message' => 'Department created successfully.'], 201);
    }

    public function show(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $request->user()->can('departments.view')) {
            return $this->forbidden();
        }
        $department = $this->department($tenant, $id)?->loadCount('users');

        return $department ? response()->json(['data' => $this->serialize($department)]) : $this->notFound();
    }

    public function update(UpdateDepartmentRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $request->user()->can('departments.update')) {
            return $this->forbidden();
        }
        $department = $this->department($tenant, $id);
        if (! $department) {
            return $this->notFound();
        }
        $validated = $request->validated();
        $before = $department->only(array_keys($validated));
        $department->update($validated);
        $this->log($request, $tenant, $department, 'department.updated', [
            'before' => $before, 'after' => $department->only(array_keys($validated)),
        ]);

        return response()->json(['data' => $this->serialize($department), 'message' => 'Department updated successfully.']);
    }

    public function status(UpdateDepartmentStatusRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $request->user()->can('departments.update')) {
            return $this->forbidden();
        }
        $department = $this->department($tenant, $id);
        if (! $department) {
            return $this->notFound();
        }
        $before = $department->status;
        $department->update(['status' => $request->validated('status')]);
        $this->log($request, $tenant, $department, 'department.status_changed', [
            'before' => $before, 'after' => $department->status,
        ]);

        return response()->json(['data' => $this->serialize($department), 'message' => 'Department status updated successfully.']);
    }

    public function destroy(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $request->user()->can('departments.delete')) {
            return $this->forbidden();
        }
        $department = $this->department($tenant, $id);
        if (! $department) {
            return $this->notFound();
        }
        $this->log($request, $tenant, $department, 'department.deleted', [
            'assigned_users_preserved' => $department->users()->count(),
        ]);
        $department->delete();

        return response()->json(['message' => 'Department deleted successfully.']);
    }

    public function users(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $request->user()->can('departments.view')) {
            return $this->forbidden();
        }
        $department = $this->department($tenant, $id);
        if (! $department) {
            return $this->notFound();
        }
        $validated = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $page = $department->users()->where('company_id', $tenant->companyId())
            ->with(['roles:id,name', 'department:id,name,code,status,deleted_at'])->paginate($validated['per_page'] ?? 15);

        return response()->json(['users' => UserResource::collection($page->items()), 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
        ]]);
    }

    private function department(TenantContext $tenant, int $id): ?Department
    {
        return Department::query()->forCompany($tenant->companyId())->find($id);
    }

    private function serialize(Department $department): array
    {
        return array_merge($department->only(['id', 'name', 'code', 'description', 'status', 'created_by', 'created_at', 'updated_at']), [
            'employee_count' => $department->users_count ?? $department->users()->count(),
        ]);
    }

    private function log(Request $request, TenantContext $tenant, Department $department, string $event, array $changes): void
    {
        activity('departments')->causedBy($request->user())->performedOn($department)->event($event)
            ->withProperties(['company_id' => $tenant->companyId(), 'department_id' => $department->id, 'changes' => $changes])->log($event);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Department not found.'], 404);
    }
}
