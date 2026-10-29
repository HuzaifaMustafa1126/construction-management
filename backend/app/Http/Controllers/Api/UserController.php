<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListUsersRequest;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Requests\Api\V1\UpdateUserStatusRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\UserAccountService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UserController extends Controller
{
    public function index(ListUsersRequest $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->can('users.view')) {
            return $this->forbidden();
        }
        $validated = $request->validated();
        $query = User::query()->with(['company:id,name,slug', 'roles:id,name', 'department:id,name,code,status,deleted_at'])
            ->where('company_id', $tenant->companyId());

        if ($search = $validated['search'] ?? null) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        if ($status = $validated['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($role = $validated['role'] ?? null) {
            $query->whereHas('roles', fn ($roles) => $roles->where('roles.name', $role)
                ->where('roles.company_id', $tenant->companyId()));
        }
        if ($departmentId = $validated['department_id'] ?? null) {
            $query->where('department_id', $departmentId)
                ->whereHas('department', fn ($departments) => $departments
                    ->where('company_id', $tenant->companyId())->whereNull('deleted_at'));
        }

        $paginator = $query->orderBy($validated['sort'] ?? 'created_at', $validated['direction'] ?? 'desc')
            ->paginate($validated['per_page'] ?? 15)->withQueryString();

        return response()->json([
            'users' => UserResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->can('users.create') || ! $request->user()->can('roles.assign')) {
            return $this->forbidden();
        }
        $validated = $request->validated();
        $role = $this->assignableRole($request, $tenant, $validated['role']);
        if (! $role) {
            return response()->json(['message' => 'The selected role is invalid.', 'errors' => [
                'role' => ['The role must belong to the current company.'],
            ]], 422);
        }

        $user = DB::transaction(function () use ($request, $tenant, $validated, $role): User {
            $user = new User([
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'first_name' => $validated['first_name'], 'last_name' => $validated['last_name'],
                'email' => $validated['email'], 'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']), 'role' => $this->legacyRole($role->name),
                'status' => User::STATUS_ACTIVE, 'department' => $validated['department'] ?? null,
            ]);
            $user->company_id = $tenant->companyId();
            $user->save();
            $user->assignRole($role);
            $this->log($request, $tenant, $user, 'user.created', [
                'fields' => ['name', 'first_name', 'last_name', 'email', 'phone', 'department'], 'role' => $role->name,
            ]);

            return $user;
        });

        return response()->json(['message' => 'User created successfully.',
            'user' => array_merge($user->only([
                'id', 'first_name', 'last_name', 'email', 'phone', 'role', 'status', 'department',
            ]), ['roles' => [$role->name]])], 201);
    }

    public function show(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $request->user()->can('users.view')) {
            return $this->forbidden();
        }
        $user = $this->user($tenant, $id);

        return $user ? response()->json(['user' => new UserResource(
            $user->load(['company:id,name,slug', 'roles:id,name', 'department:id,name,code,status,deleted_at']),
        )]) : $this->notFound();
    }

    public function update(UpdateUserRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $request->user()->can('users.update')) {
            return $this->forbidden();
        }
        $user = $this->user($tenant, $id);
        if (! $user) {
            return $this->notFound();
        }
        $validated = $request->validated();
        $before = $user->only(array_keys($validated));
        $user->fill($validated);
        if (! array_key_exists('name', $validated)
            && (array_key_exists('first_name', $validated) || array_key_exists('last_name', $validated))) {
            $user->name = trim($user->first_name.' '.$user->last_name);
        }
        $user->save();
        $this->log($request, $tenant, $user, 'user.profile_updated', [
            'before' => $before, 'after' => $user->only(array_keys($validated)),
        ]);

        return response()->json(['message' => 'User updated successfully.',
            'user' => new UserResource($user->load(['company:id,name,slug', 'roles:id,name', 'department:id,name,code,status,deleted_at']))]);
    }

    public function status(UpdateUserStatusRequest $request, TenantContext $tenant, UserAccountService $accounts, int $id): JsonResponse
    {
        if (! $request->user()->hasRole('company_admin') || ! $request->user()->can('users.update')) {
            return $this->forbidden();
        }
        $user = $this->user($tenant, $id);
        if (! $user) {
            return $this->notFound();
        }
        $status = $request->validated('status');
        if ($user->status === User::STATUS_ACTIVE && $status !== User::STATUS_ACTIVE
            && $user->hasRole('company_admin') && $this->activeAdminCount($tenant->companyId()) <= 1) {
            return response()->json(['message' => 'The last active Company Admin cannot be suspended.'], 422);
        }
        $before = $user->status;
        DB::transaction(function () use ($accounts, $before, $request, $status, $tenant, $user): void {
            $user->update(['status' => $status]);
            if ($status !== User::STATUS_ACTIVE) {
                $accounts->revokeAccess($user);
            }
            $this->log($request, $tenant, $user, 'user.status_changed', ['before' => $before, 'after' => $status]);
        });

        return response()->json(['message' => 'User status updated successfully.',
            'user' => new UserResource($user->load(['company:id,name,slug', 'roles:id,name', 'department:id,name,code,status,deleted_at']))]);
    }

    public function destroy(Request $request, TenantContext $tenant, UserAccountService $accounts, int $id): JsonResponse
    {
        if (! $request->user()->can('users.delete')) {
            return $this->forbidden();
        }
        $user = $this->user($tenant, $id);
        if (! $user) {
            return $this->notFound();
        }
        if ($request->user()->is($user)) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }
        if ($user->isPlatformAdmin()) {
            return response()->json(['message' => 'Platform administrators cannot be deleted from company user management.'], 403);
        }
        if ($user->status === User::STATUS_ACTIVE && $user->hasRole('company_admin')
            && $this->activeAdminCount($tenant->companyId()) <= 1) {
            return response()->json(['message' => 'The last active Company Admin cannot be deleted.'], 422);
        }
        try {
            DB::transaction(function () use ($accounts, $request, $tenant, $user): void {
                $this->log($request, $tenant, $user, 'user.permanently_deleted', [
                    'email' => $user->email,
                    'deleted_at' => now()->toIso8601String(),
                ]);
                $accounts->permanentlyDelete($user);
            });
        } catch (ConflictHttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'User permanently deleted successfully.']);
    }

    private function assignableRole(Request $request, TenantContext $tenant, string $name): ?Role
    {
        $mapped = ['admin' => 'company_admin', 'supervisor' => 'site_supervisor'][$name] ?? $name;
        $role = Role::query()->where('company_id', $tenant->companyId())->where('name', $mapped)
            ->with('permissions:id,name')->first();

        return $role && ! $role->permissions->contains(fn ($permission) => ! $request->user()->can($permission->name))
            ? $role : null;
    }

    private function legacyRole(string $role): string
    {
        return ['company_admin' => 'admin', 'site_supervisor' => 'supervisor'][$role] ?? 'client';
    }

    private function activeAdminCount(int $companyId): int
    {
        return User::query()->where('company_id', $companyId)->where('status', User::STATUS_ACTIVE)
            ->role('company_admin')->count();
    }

    private function user(TenantContext $tenant, int $id): ?User
    {
        return User::query()->where('company_id', $tenant->companyId())->find($id);
    }

    private function log(Request $request, TenantContext $tenant, User $target, string $event, array $changes): void
    {
        activity('users')->causedBy($request->user())->performedOn($target)->event($event)
            ->withProperties(['company_id' => $tenant->companyId(), 'target_user_id' => $target->id,
                'changes' => $changes])->log($event);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'User not found.'], 404);
    }
}
