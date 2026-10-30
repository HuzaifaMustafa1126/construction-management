<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'roles' => $this->whenLoaded('invitationRoles', fn () => $this->invitationRoles
                ->pluck('role_name')->sort()->values()->all()),
            'invited_by' => $this->whenLoaded('inviter', fn () => $this->inviter ? [
                'id' => $this->inviter->id,
                'name' => $this->inviter->name,
            ] : null),
            'status' => $this->status(),
            'expires_at' => $this->expires_at,
            'accepted_at' => $this->accepted_at,
            'cancelled_at' => $this->cancelled_at,
            'expired_at' => $this->expired_at,
            'last_sent_at' => $this->last_sent_at,
            'send_count' => $this->send_count,
            'created_at' => $this->created_at,
        ];
    }
}
