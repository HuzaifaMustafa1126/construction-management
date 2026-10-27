<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeasurementUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'symbol' => $this->symbol, 'unit_type' => $this->unit_type, 'decimal_precision' => $this->decimal_precision, 'is_system' => $this->is_system, 'is_active' => $this->is_active, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
