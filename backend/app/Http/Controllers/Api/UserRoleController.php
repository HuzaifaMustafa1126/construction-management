<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class UserRoleController extends Controller
{
    public function update(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $request->user()->can('roles.assign')) {
            return $this->forbidden();
        }

        $target = User::query()->where('company_id', $tenant->companyId())->find($id);
        if (! $target) {
            return response()->json(['message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct'],
            'company_id' => ['prohibited'],
        ]);

        $roles = Role::query()
            ->where('company_id', $tenant->companyId())
            ->whereIn('name', $validated['roles'])
            ->with('permissions:id,name')
            ->get();

        if ($roles->count() !== count($validated['roles'])) {
            return response()->json([
                'message' => 'One or more selected roles are invalid.',
                'errors' => ['roles' => ['Roles must belong to the current company.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($roles->flatMap->permissions->contains(
            fn ($permission) => ! $request->user()->can($permission->name),
        )) {
            return $this->forbidden();
        }

        $current = $target->roles()->pluck('name')->all();
        if (in_array('company_admin', $current, true)
            && ! in_array('company_admin', $validated['roles'], true)
            && $this->activeAdminCount($tenant->companyId()) <= 1) {
            return response()->json([
                'message' => 'The last active Company Admin cannot be removed.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::transaction(function () use ($request, $target, $roles, $current, $tenant): void {
            $next = $roles->pluck('name')->all();
            $target->syncRoles($roles);

            foreach (array_diff($next, $current) as $assigned) {
                $this->log($request, $target, $tenant->companyId(), 'user.role_assigned', $assigned);
            }
            foreach (array_diff($current, $next) as $removed) {
                $this->log($request, $target, $tenant->companyId(), 'user.role_removed', $removed);
            }
        });

        $target->unsetRelation('roles');

        return response()->json([
            'message' => 'User roles updated successfully.',
            'data' => [
                'user_id' => $target->id,
                'roles' => $target->roles()->pluck('name')->sort()->values()->all(),
            ],
        ]);
    }

    private function activeAdminCount(int $companyId): int
    {
        $roleId = Role::query()
            ->where('company_id', $companyId)
            ->where('name', 'company_admin')
            ->value('id');

        return User::query()
            ->where('users.company_id', $companyId)
            ->where('users.status', User::STATUS_ACTIVE)
            ->join('model_has_roles', function ($join) use ($companyId, $roleId): void {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->where('model_has_roles.company_id', $companyId)
                    ->where('model_has_roles.role_id', $roleId);
            })->count();
    }

    private function log(Request $request, User $target, int $companyId, string $event, string $role): void
    {
        activity('rbac')->causedBy($request->user())->performedOn($target)->event($event)
            ->withProperties([
                'company_id' => $companyId,
                'target_user_id' => $target->id,
                'role' => $role,
            ])->log($event);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }
}
