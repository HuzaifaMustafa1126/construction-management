<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectInventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $precision = (int) $this->material->measurementUnit->decimal_precision;

        return ['id' => $this->id, 'project' => $this->whenLoaded('project', fn () => ['id' => $this->project->id, 'name' => $this->project->name, 'code' => $this->project->code]), 'project_id' => $this->project_id, 'material_id' => $this->material_id, 'material' => ['id' => $this->material->id, 'name' => $this->material->name, 'code' => $this->material->code, 'is_active' => $this->material->is_active, 'category' => ['id' => $this->material->category->id, 'name' => $this->material->category->name], 'measurement_unit' => ['id' => $this->material->measurementUnit->id, 'name' => $this->material->measurementUnit->name, 'symbol' => $this->material->measurementUnit->symbol, 'decimal_precision' => $precision]], 'minimum_stock_level' => $this->decimalString($this->minimum_stock_level, $precision), 'current_stock' => $this->decimalString($this->current_stock ?? '0', $precision), 'opening_stock_recorded' => (bool) ($this->opening_stock_recorded ?? false), 'creator' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]), 'created_at' => $this->created_at];
    }

    private function decimalString(mixed $value, int $precision): string
    {
        $parts = explode('.', (string) $value, 2);
        if ($precision === 0) {
            return $parts[0];
        }

        return $parts[0].'.'.str_pad(substr($parts[1] ?? '', 0, $precision), $precision, '0');
    }
}
