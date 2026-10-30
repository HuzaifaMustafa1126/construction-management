<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStaffInvitationRequest;
use App\Http\Resources\Api\V1\StaffInvitationResource;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class StaffInvitationController extends Controller
{
    public function index(Request $request, TenantContext $tenant): JsonResponse
    {
        if (! $this->authorized($request)) return $this->forbidden();

        $this->expireStale($tenant->companyId());
        $invitations = StaffInvitation::query()
            ->where('company_id', $tenant->companyId())
            ->with(['invitationRoles', 'inviter:id,name,first_name,last_name,deleted_at'])
            ->latest()->limit(100)->get();

        return response()->json(['data' => StaffInvitationResource::collection($invitations)]);
    }

    public function store(StoreStaffInvitationRequest $request, TenantContext $tenant): JsonResponse
    {
        if (! $this->authorized($request)) return $this->forbidden();

        $validated = $request->validated();
        if (User::withTrashed()->where('email', $validated['email'])->exists()) {
            return $this->emailUnavailable();
        }
        $roles = $this->assignableRoles($request, $tenant->companyId(), $validated['roles']);
        if (! $roles) return $this->invalidRoles();

        $token = Str::random(64);
        $pendingHash = hash('sha256', $validated['email']);

        try {
            $invitation = DB::transaction(function () use ($pendingHash, $request, $roles, $tenant, $token, $validated): StaffInvitation {
                $existing = StaffInvitation::query()->where('pending_email_hash', $pendingHash)->lockForUpdate()->first();
                if ($existing) {
                    if ($existing->expires_at->isPast()) {
                        $this->markExpired($existing);
                    } else {
                        throw new \DomainException('pending');
                    }
                }

                $invitation = new StaffInvitation([
                    'email' => $validated['email'],
                    'pending_email_hash' => $pendingHash,
                    'token_hash' => hash('sha256', $token),
                    'invited_by' => $request->user()->id,
                    'expires_at' => now()->addHours(config('invitations.expires_hours', 72)),
                ]);
                $invitation->company_id = $tenant->companyId();
                $invitation->save();
                $invitation->invitationRoles()->createMany($roles->map(fn (Role $role) => [
                    'role_id' => $role->id, 'role_name' => $role->name,
                ])->all());
                $this->log($request, $invitation, 'staff_invitation.created', $roles->pluck('name')->all());

                return $invitation;
            });
        } catch (\DomainException) {
            return $this->emailUnavailable();
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') return $this->emailUnavailable();
            throw $exception;
        }

        $this->deliver($invitation, $token);

        return response()->json([
            'message' => 'Staff invitation sent successfully.',
            'data' => new StaffInvitationResource($invitation->load(['invitationRoles', 'inviter:id,name,first_name,last_name,deleted_at'])),
        ], 201);
    }

    public function resend(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $this->authorized($request)) return $this->forbidden();

        $token = Str::random(64);
        $invitation = DB::transaction(function () use ($id, $request, $tenant, $token): ?StaffInvitation {
            $invitation = StaffInvitation::query()->where('company_id', $tenant->companyId())->lockForUpdate()->find($id);
            if (! $invitation || $invitation->accepted_at || $invitation->cancelled_at) return null;
            if (User::withTrashed()->where('email', $invitation->email)->exists()) return null;

            $roleNames = $invitation->invitationRoles()->pluck('role_name')->all();
            $roles = $this->assignableRoles($request, $tenant->companyId(), $roleNames);
            if (! $roles) return null;
            $pendingHash = hash('sha256', $invitation->email);
            if (StaffInvitation::query()->where('pending_email_hash', $pendingHash)
                ->whereKeyNot($invitation->id)->exists()) return null;

            $invitation->update([
                'pending_email_hash' => $pendingHash,
                'token_hash' => hash('sha256', $token),
                'invited_by' => $request->user()->id,
                'expires_at' => now()->addHours(config('invitations.expires_hours', 72)),
                'expired_at' => null,
            ]);
            $this->log($request, $invitation, 'staff_invitation.resent', $roleNames);

            return $invitation;
        });

        if (! $invitation) return $this->unavailable();
        $this->deliver($invitation, $token);

        return response()->json([
            'message' => 'Staff invitation resent successfully.',
            'data' => new StaffInvitationResource($invitation->load(['invitationRoles', 'inviter:id,name,first_name,last_name,deleted_at'])),
        ]);
    }

    public function cancel(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $this->authorized($request)) return $this->forbidden();

        $invitation = DB::transaction(function () use ($id, $request, $tenant): ?StaffInvitation {
            $invitation = StaffInvitation::query()->where('company_id', $tenant->companyId())->lockForUpdate()->find($id);
            if (! $invitation || ! $invitation->isPending()) return null;
            $invitation->update([
                'cancelled_at' => now(), 'token_hash' => null, 'pending_email_hash' => null,
            ]);
            $this->log($request, $invitation, 'staff_invitation.cancelled', $invitation->invitationRoles()->pluck('role_name')->all());

            return $invitation;
        });

        return $invitation
            ? response()->json(['message' => 'Staff invitation cancelled successfully.'])
            : $this->unavailable();
    }

    private function authorized(Request $request): bool
    {
        return $request->user()->can('users.create') && $request->user()->can('roles.assign');
    }

    private function assignableRoles(Request $request, int $companyId, array $names): ?Collection
    {
        $roles = Role::query()->where('company_id', $companyId)->whereIn('name', $names)
            ->with('permissions:id,name')->get();
        if ($roles->count() !== count($names)) return null;
        if ($roles->flatMap->permissions->contains(fn ($permission) => ! $request->user()->can($permission->name))) return null;

        return $roles;
    }

    private function expireStale(int $companyId): void
    {
        StaffInvitation::query()->where('company_id', $companyId)
            ->whereNull('accepted_at')->whereNull('cancelled_at')->whereNull('expired_at')
            ->where('expires_at', '<=', now())->update([
                'expired_at' => now(), 'token_hash' => null, 'pending_email_hash' => null, 'updated_at' => now(),
            ]);
    }

    private function markExpired(StaffInvitation $invitation): void
    {
        $invitation->update(['expired_at' => now(), 'token_hash' => null, 'pending_email_hash' => null]);
    }

    private function deliver(StaffInvitation $invitation, string $token): void
    {
        $invitation->loadMissing('company');
        Notification::route('mail', $invitation->email)->notify(new StaffInvitationNotification($invitation, $token));
        $invitation->forceFill(['last_sent_at' => now(), 'send_count' => $invitation->send_count + 1])->save();
    }

    private function log(Request $request, StaffInvitation $invitation, string $event, array $roles): void
    {
        activity('users')->causedBy($request->user())->performedOn($invitation)->event($event)
            ->withProperties(['company_id' => $invitation->company_id, 'invitation_id' => $invitation->id,
                'email' => $invitation->email, 'roles' => $roles])->log($event);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function emailUnavailable(): JsonResponse
    {
        return response()->json(['message' => 'An account or pending invitation already uses this email.',
            'errors' => ['email' => ['This email is not available for invitation.']]], 422);
    }

    private function invalidRoles(): JsonResponse
    {
        return response()->json(['message' => 'One or more selected roles are invalid.',
            'errors' => ['roles' => ['Roles must belong to this company and be assignable by you.']]], 422);
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['message' => 'This invitation is no longer available.'], 422);
    }
}
