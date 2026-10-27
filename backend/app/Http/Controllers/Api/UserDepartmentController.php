<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignUserDepartmentRequest;
use App\Models\Department;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserDepartmentController extends Controller
{
    public function update(AssignUserDepartmentRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $request->user()->can('departments.assign')) {
            return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
        }
        $user = User::query()->where('company_id', $tenant->companyId())->find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $departmentId = $request->validated('department_id');
        $department = null;
        if ($departmentId !== null) {
            $department = Department::query()->forCompany($tenant->companyId())->find($departmentId);
            if (! $department) {
                return response()->json(['message' => 'Department not found.'], 404);
            }
            if (! $department->isActive()) {
                return response()->json(['message' => 'Only active departments can receive assignments.'], 422);
            }
        }

        $before = $user->department_id;
        DB::transaction(function () use ($user, $departmentId, $request, $tenant, $before): void {
            $user->update(['department_id' => $departmentId]);
            $event = $departmentId === null ? 'department.user_unassigned' : 'department.user_assigned';
            activity('departments')->causedBy($request->user())->performedOn($user)->event($event)
                ->withProperties([
                    'company_id' => $tenant->companyId(), 'target_user_id' => $user->id,
                    'department_id' => $departmentId, 'previous_department_id' => $before,
                ])->log($event);
        });

        return response()->json(['message' => $department ? 'Department assigned successfully.' : 'Department unassigned successfully.',
            'data' => ['user_id' => $user->id, 'department_id' => $departmentId]]);
    }
}
