<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'phone' => $this->phone,
            'role' => $this->role,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->sort()->values()->all()),
            'status' => $this->status,
            'department' => $this->department,
            'department_id' => $this->department_id,
            'department_details' => $this->whenLoaded('department', fn () => $this->resource->getRelation('department') ? [
                'id' => $this->resource->getRelation('department')->id,
                'name' => $this->resource->getRelation('department')->name,
                'code' => $this->resource->getRelation('department')->code,
                'status' => $this->resource->getRelation('department')->status,
                'deleted_at' => $this->resource->getRelation('department')->deleted_at,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn () => $this->company ? [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'slug' => $this->company->slug,
            ] : null),
        ];
    }
}
