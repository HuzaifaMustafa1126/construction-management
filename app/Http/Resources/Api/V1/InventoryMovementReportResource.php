<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $precision = (int) ($this->projectInventory?->material?->measurementUnit?->decimal_precision ?? 4);
        [$sourceType, $sourceId, $sourceNumber, $reason] = $this->source();

        return ['id' => $this->id, 'project' => ['id' => $this->project_id, 'name' => $this->project?->name, 'code' => $this->project?->code], 'inventory_id' => $this->project_inventory_id, 'material' => ['id' => $this->material_id, 'name' => $this->projectInventory?->material?->name, 'code' => $this->projectInventory?->material?->code], 'measurement_unit' => ['id' => $this->projectInventory?->material?->measurementUnit?->id, 'name' => $this->projectInventory?->material?->measurementUnit?->name, 'symbol' => $this->projectInventory?->material?->measurementUnit?->symbol], 'transaction_type' => $this->transaction_type, 'direction' => $this->direction, 'quantity' => $this->decimal($this->quantity, $precision), 'movement_date' => $this->transaction_date?->format('Y-m-d'), 'source_document_type' => $sourceType, 'source_document_id' => $sourceId, 'source_document_number' => $sourceNumber, 'reason' => $reason, 'actor' => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null, 'notes' => $this->notes, 'created_at' => $this->created_at];
    }

    private function source(): array
    {
        if ($this->deliveryItem?->delivery) {
            return ['material_delivery', $this->deliveryItem->delivery->id, $this->deliveryItem->delivery->delivery_number, null];
        }
        if ($this->consumptionItem?->consumption) {
            return ['material_consumption', $this->consumptionItem->consumption->id, $this->consumptionItem->consumption->consumption_number, null];
        }
        if ($this->stockAdjustmentItem?->adjustment) {
            return ['stock_adjustment', $this->stockAdjustmentItem->adjustment->id, $this->stockAdjustmentItem->adjustment->adjustment_number, $this->stockAdjustmentItem->reason_code];
        }

        return ['opening_stock', null, null, null];
    }

    private function decimal(mixed $value, int $precision): string
    {
        $parts = explode('.', (string) $value, 2);

        return $precision === 0 ? $parts[0] : $parts[0].'.'.str_pad(substr($parts[1] ?? '', 0, $precision), $precision, '0');
    }
}
