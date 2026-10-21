<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $precision = (int) ($this->projectInventory?->material?->measurementUnit?->decimal_precision ?? 4);

        return ['id' => $this->id, 'transaction_type' => $this->transaction_type, 'direction' => $this->direction, 'quantity' => $this->decimalString($this->quantity, $precision), 'transaction_date' => $this->transaction_date?->format('Y-m-d'), 'notes' => $this->notes, 'creator' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]), 'created_at' => $this->created_at];
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
