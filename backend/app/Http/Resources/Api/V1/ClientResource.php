<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_name' => $this->company_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'notes' => $this->notes,
            'has_login_account' => array_key_exists('user_id', $this->resource->getAttributes()) ? $this->user_id !== null : null,
            'account' => $this->when($request->user()?->can('users.view') && $this->resource->relationLoaded('user'), function () {
                $user = $this->resource->getRelation('user');

                return $user ? ['id' => $user->id, 'email' => $user->email, 'status' => $user->status,
                    'deleted' => $user->trashed()] : null;
            }),
            'projects_count' => $this->whenCounted('projects'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
