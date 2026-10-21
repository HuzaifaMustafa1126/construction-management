<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialDeliveryItemResource extends JsonResource
{
    public function toArray(Request $r): array
    {
        $p = (int) $this->material->measurementUnit->decimal_precision;

        return ['id' => $this->id, 'project_inventory_id' => $this->project_inventory_id, 'material' => ['id' => $this->material->id, 'name' => $this->material->name, 'code' => $this->material->code, 'measurement_unit' => ['id' => $this->material->measurementUnit->id, 'name' => $this->material->measurementUnit->name, 'symbol' => $this->material->measurementUnit->symbol, 'decimal_precision' => $p]], 'quantity' => $this->decimal($this->quantity, $p), 'notes' => $this->notes, 'inventory_transaction_ids' => $this->whenLoaded('inventoryTransactions', fn () => $this->inventoryTransactions->pluck('id')->values())];
    }

    private function decimal(mixed $v, int $p): string
    {
        $x = explode('.', (string) $v, 2);

        return $p === 0 ? $x[0] : $x[0].'.'.str_pad(substr($x[1] ?? '', 0, $p), $p, '0');
    }
}
