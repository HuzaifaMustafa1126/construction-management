<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $r): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'contact_person' => $this->contact_person, 'phone' => $this->phone, 'email' => $this->email, 'address' => $this->address, 'notes' => $this->notes, 'is_active' => $this->is_active, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
