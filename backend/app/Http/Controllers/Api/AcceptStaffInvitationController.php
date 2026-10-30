<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AcceptStaffInvitationRequest;
use App\Http\Requests\Api\V1\InspectStaffInvitationRequest;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AcceptStaffInvitationController extends Controller
{
    private const INVALID_MESSAGE = 'This invitation is invalid or no longer available.';

    public function inspect(InspectStaffInvitationRequest $request): JsonResponse
    {
        $invitation = StaffInvitation::query()
            ->where('token_hash', hash('sha256', $request->validated('token')))
            ->with(['company:id,name,status,deleted_at', 'invitationRoles', 'inviter'])
            ->first();

        if (! $invitation || ! $this->isAcceptable($invitation)) return $this->invalid();

        return response()->json(['data' => [
            'email' => $invitation->email,
            'company' => ['name' => $invitation->company->name],
            'roles' => $invitation->invitationRoles->pluck('role_name')->sort()->values()->all(),
            'expires_at' => $invitation->expires_at,
        ]]);
    }

    public function accept(AcceptStaffInvitationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        try {
            $accepted = DB::transaction(function () use ($validated): bool {
                $invitation = StaffInvitation::query()
                    ->where('token_hash', hash('sha256', $validated['token']))
                    ->with(['company', 'invitationRoles', 'inviter'])
                    ->lockForUpdate()->first();

                if (! $invitation) return false;
                if ($invitation->expires_at->isPast() && ! $invitation->accepted_at && ! $invitation->cancelled_at) {
                    $invitation->update([
                        'expired_at' => now(), 'token_hash' => null, 'pending_email_hash' => null,
                    ]);

                    return false;
                }
                if (! $this->isAcceptable($invitation)) return false;

                $roleIds = $invitation->invitationRoles->pluck('role_id')->filter()->values();
                if ($roleIds->count() !== $invitation->invitationRoles->count()) return false;
                $roles = Role::query()->where('company_id', $invitation->company_id)
                    ->whereIn('id', $roleIds)->with('permissions:id,name')->get();
                if ($roles->count() !== $roleIds->count()) return false;

                $inviter = $invitation->inviter;
                $previousTeamId = getPermissionsTeamId();
                try {
                    setPermissionsTeamId($invitation->company_id);
                    app(PermissionRegistrar::class)->forgetCachedPermissions();
                    $inviter->unsetRelation('roles')->unsetRelation('permissions');
                    if (! $inviter->can('users.create') || ! $inviter->can('roles.assign')) return false;
                    if ($roles->flatMap->permissions->contains(
                        fn ($permission) => ! $inviter->can($permission->name),
                    )) return false;
                    if (User::withTrashed()->where('email', $invitation->email)->exists()) return false;

                    $primaryRole = $roles->sortBy('id')->first()->name;
                    $user = new User([
                        'name' => trim($validated['first_name'].' '.$validated['last_name']),
                        'first_name' => $validated['first_name'],
                        'last_name' => $validated['last_name'],
                        'email' => $invitation->email,
                        'phone' => $validated['phone'] ?? null,
                        'password' => $validated['password'],
                        'role' => $this->legacyRole($primaryRole),
                        'status' => User::STATUS_ACTIVE,
                    ]);
                    $user->company_id = $invitation->company_id;
                    $user->email_verified_at = now();
                    $user->save();
                    $user->syncRoles($roles);

                    $invitation->update([
                        'accepted_user_id' => $user->id,
                        'accepted_at' => now(),
                        'token_hash' => null,
                        'pending_email_hash' => null,
                    ]);
                    activity('users')->causedBy($inviter)->performedOn($invitation)
                        ->event('staff_invitation.accepted')->withProperties([
                            'company_id' => $invitation->company_id,
                            'invitation_id' => $invitation->id,
                            'accepted_user_id' => $user->id,
                            'roles' => $roles->pluck('name')->all(),
                        ])->log('staff_invitation.accepted');
                } finally {
                    setPermissionsTeamId($previousTeamId);
                    $inviter->unsetRelation('roles')->unsetRelation('permissions');
                    app(PermissionRegistrar::class)->forgetCachedPermissions();
                }

                return true;
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') return $this->invalid();
            throw $exception;
        }

        return $accepted
            ? response()->json(['message' => 'Invitation accepted successfully. You can now sign in.'])
            : $this->invalid();
    }

    private function isAcceptable(StaffInvitation $invitation): bool
    {
        if (! $invitation->isPending() || ! $invitation->company?->isOperational()) return false;
        $inviter = $invitation->inviter;
        if (! $inviter || $inviter->trashed() || ! $inviter->isActive()
            || (int) $inviter->company_id !== (int) $invitation->company_id) return false;

        $roleIds = $invitation->invitationRoles->pluck('role_id')->filter()->values();
        if ($roleIds->count() !== $invitation->invitationRoles->count()) return false;
        $roles = Role::query()->where('company_id', $invitation->company_id)
            ->whereIn('id', $roleIds)->with('permissions:id,name')->get();
        if ($roles->count() !== $roleIds->count()) return false;

        $previousTeamId = getPermissionsTeamId();
        try {
            setPermissionsTeamId($invitation->company_id);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $inviter->unsetRelation('roles')->unsetRelation('permissions');

            return $inviter->can('users.create') && $inviter->can('roles.assign')
                && ! $roles->flatMap->permissions->contains(
                    fn ($permission) => ! $inviter->can($permission->name),
                )
                && ! User::withTrashed()->where('email', $invitation->email)->exists();
        } finally {
            setPermissionsTeamId($previousTeamId);
            $inviter->unsetRelation('roles')->unsetRelation('permissions');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function legacyRole(string $role): string
    {
        return ['company_admin' => 'admin', 'site_supervisor' => 'supervisor'][$role] ?? 'client';
    }

    private function invalid(): JsonResponse
    {
        return response()->json(['message' => self::INVALID_MESSAGE], 422);
    }
}
