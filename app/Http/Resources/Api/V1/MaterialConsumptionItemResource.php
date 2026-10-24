<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialConsumptionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unit = $this->material?->measurementUnit;
        $precision = (int) ($unit?->decimal_precision ?? 4);

        return ['id' => $this->id, 'project_inventory_id' => $this->project_inventory_id, 'material' => $this->whenLoaded('material', fn () => ['id' => $this->material->id, 'name' => $this->material->name, 'code' => $this->material->code]), 'measurement_unit' => $unit ? ['id' => $unit->id, 'name' => $unit->name, 'symbol' => $unit->symbol, 'decimal_precision' => $precision] : null, 'requested_quantity' => $this->decimalString($this->quantity, $precision), 'posted_quantity' => $this->when($this->relationLoaded('inventoryTransactions') && $this->inventoryTransactions->isNotEmpty(), $this->decimalString($this->quantity, $precision)), 'current_stock' => $this->when(isset($this->current_stock), $this->decimalString($this->current_stock, $precision)), 'notes' => $this->notes, 'inventory_transaction_ids' => $this->whenLoaded('inventoryTransactions', fn () => $this->inventoryTransactions->pluck('id')->values())];
    }

    private function decimalString(mixed $value, int $precision): string
    {
        $parts = explode('.', (string) $value, 2);

        return $precision === 0 ? $parts[0] : $parts[0].'.'.str_pad(substr($parts[1] ?? '', 0, $precision), $precision, '0');
    }
}
