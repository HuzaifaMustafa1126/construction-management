<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Authorization\PermissionCatalog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    public function index(Request $request, TenantContext $tenant): JsonResponse
    {
        if ($denied = $this->deniedUnless($request, 'roles.view')) {
            return $denied;
        }

        return response()->json(['data' => Role::query()
            ->where('company_id', $tenant->companyId())
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->serialize($role))]);
    }

    public function store(Request $request, TenantContext $tenant): JsonResponse
    {
        if ($denied = $this->deniedUnless($request, 'roles.create')) {
            return $denied;
        }

        $validated = $this->validateRole($request, $tenant->companyId());

        if (! $this->mayGrant($request, $validated['permissions'])) {
            return $this->forbidden();
        }

        $role = DB::transaction(function () use ($request, $tenant, $validated): Role {
            $role = Role::query()->create([
                'company_id' => $tenant->companyId(),
                'name' => $validated['name'],
                'guard_name' => 'web',
            ]);
            $role->syncPermissions($validated['permissions']);
            $this->log($request, $tenant, $role, 'role.created', ['permissions' => $validated['permissions']]);

            return $role;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['data' => $this->serialize($role->load('permissions:id,name'))], Response::HTTP_CREATED);
    }

    public function show(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        if ($denied = $this->deniedUnless($request, 'roles.view')) {
            return $denied;
        }

        $role = $this->role($tenant, $id);

        return $role
            ? response()->json(['data' => $this->serialize($role->load('permissions:id,name'))])
            : response()->json(['message' => 'Role not found.'], 404);
    }

    public function update(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        if ($denied = $this->deniedUnless($request, 'roles.update')) {
            return $denied;
        }

        $role = $this->role($tenant, $id);
        if (! $role) {
            return response()->json(['message' => 'Role not found.'], 404);
        }
        if ($role->name === 'company_admin') {
            return response()->json(['message' => 'The company_admin role is protected.'], 422);
        }

        $validated = $this->validateRole($request, $tenant->companyId(), $role->id);
        if (! $this->mayGrant($request, $validated['permissions'])) {
            return $this->forbidden();
        }

        DB::transaction(function () use ($request, $tenant, $role, $validated): void {
            $before = ['name' => $role->name, 'permissions' => $role->permissions()->pluck('name')->all()];
            $preserved = $role->permissions()
                ->pluck('name')
                ->filter(fn (string $permission) => ! PermissionCatalog::isSelectable(PermissionCatalog::canonical($permission))
                    && ! array_key_exists($permission, PermissionCatalog::LEGACY_TO_CANONICAL))
                ->all();
            $role->update(['name' => $validated['name']]);
            $role->syncPermissions(array_values(array_unique([...$validated['permissions'], ...$preserved])));
            $this->log($request, $tenant, $role, 'role.updated', [
                'before' => $before,
                'after' => $validated,
            ]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['data' => $this->serialize($role->load('permissions:id,name'))]);
    }

    public function destroy(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        if ($denied = $this->deniedUnless($request, 'roles.delete')) {
            return $denied;
        }

        $role = $this->role($tenant, $id);
        if (! $role) {
            return response()->json(['message' => 'Role not found.'], 404);
        }
        if ($role->name === 'company_admin') {
            return response()->json(['message' => 'The company_admin role is protected.'], 422);
        }

        DB::transaction(function () use ($request, $tenant, $role): void {
            $this->log($request, $tenant, $role, 'role.deleted', [
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name')->all(),
            ]);
            $role->delete();
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['message' => 'Role deleted successfully.']);
    }

    private function validateRole(Request $request, int $companyId, ?int $ignore = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:125', 'regex:/^[a-z][a-z0-9_]*$/', Rule::notIn(['platform_super_admin']), Rule::unique('roles')->where(
                fn ($query) => $query->where('company_id', $companyId)->where('guard_name', 'web'),
            )->ignore($ignore)],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', Rule::in([
                ...PermissionCatalog::all(),
                ...array_keys(PermissionCatalog::LEGACY_TO_CANONICAL),
            ])],
            'company_id' => ['prohibited'],
        ]);
        $validated['permissions'] = PermissionCatalog::normalize($validated['permissions']);

        return $validated;
    }

    private function mayGrant(Request $request, array $permissions): bool
    {
        return collect($permissions)->every(fn (string $permission) => $request->user()->can($permission));
    }

    private function role(TenantContext $tenant, int $id): ?Role
    {
        return Role::query()->where('company_id', $tenant->companyId())->find($id);
    }

    private function serialize(Role $role): array
    {
        $stored = $role->permissions->pluck('name');
        $selectable = collect(PermissionCatalog::normalize($stored))
            ->filter(fn (string $permission) => PermissionCatalog::isSelectable($permission))
            ->values()
            ->all();
        $preserved = $stored
            ->filter(fn (string $permission) => ! PermissionCatalog::isSelectable(PermissionCatalog::canonical($permission))
                && ! array_key_exists($permission, PermissionCatalog::LEGACY_TO_CANONICAL))
            ->sort()
            ->values()
            ->all();

        return [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $selectable,
            'preserved_permissions' => $preserved,
        ];
    }

    private function deniedUnless(Request $request, string $permission): ?JsonResponse
    {
        return $request->user()->can($permission) ? null : $this->forbidden();
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function log(Request $request, TenantContext $tenant, Role $role, string $event, array $properties): Activity
    {
        return activity('rbac')->causedBy($request->user())->performedOn($role)->event($event)
            ->withProperties(array_merge(['company_id' => $tenant->companyId(), 'role_id' => $role->id], $properties))
            ->log($event);
    }
}
