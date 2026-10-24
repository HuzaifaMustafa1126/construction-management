<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryStockReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $precision = (int) $this->decimal_precision;

        return ['inventory_id' => $this->inventory_id, 'project' => ['id' => $this->project_id, 'name' => $this->project_name], 'material' => ['id' => $this->material_id, 'name' => $this->material_name, 'code' => $this->material_code, 'category' => ['id' => $this->category_id, 'name' => $this->category_name]], 'measurement_unit' => ['id' => $this->unit_id, 'name' => $this->unit_name, 'symbol' => $this->unit_symbol, 'decimal_precision' => $precision], 'opening_stock' => $this->decimal($this->opening_stock, $precision), 'received' => $this->decimal($this->received, $precision), 'consumed' => $this->decimal($this->consumed, $precision), 'adjustment_in' => $this->decimal($this->adjustment_in, $precision), 'adjustment_out' => $this->decimal($this->adjustment_out, $precision), 'wastage' => $this->decimal($this->wastage, $precision), 'correction_in' => $this->decimal($this->correction_in, $precision), 'correction_out' => $this->decimal($this->correction_out, $precision), 'opening_balance' => $this->decimal($this->opening_balance, $precision), 'period_in' => $this->decimal($this->period_in, $precision), 'period_out' => $this->decimal($this->period_out, $precision), 'current_stock' => $this->decimal($this->current_stock, $precision), 'closing_balance' => $this->decimal($this->current_stock, $precision), 'minimum_stock' => $this->decimal($this->minimum_stock_level, $precision), 'stock_status' => $this->stock_status, 'last_movement_date' => $this->last_movement_date];
    }

    private function decimal(mixed $value, int $precision): string
    {
        $parts = explode('.', (string) ($value ?? '0'), 2);

        return $precision === 0 ? $parts[0] : $parts[0].'.'.str_pad(substr($parts[1] ?? '', 0, $precision), $precision, '0');
    }
}
