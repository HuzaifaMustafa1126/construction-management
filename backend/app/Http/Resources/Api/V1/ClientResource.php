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
            'projects_count' => $this->whenCounted('projects'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
