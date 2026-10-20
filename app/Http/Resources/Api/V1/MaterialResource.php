<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'code' => $this->code, 'description' => $this->description, 'specification' => $this->specification, 'brand' => $this->brand, 'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'name' => $this->category->name]), 'measurement_unit' => $this->whenLoaded('measurementUnit', fn () => ['id' => $this->measurementUnit->id, 'name' => $this->measurementUnit->name, 'symbol' => $this->measurementUnit->symbol]), 'is_active' => $this->is_active, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
