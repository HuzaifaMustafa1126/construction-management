<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class InventoryStockPreviewResource extends InventoryStockReportResource
{
    public function toArray(Request $request): array
    {
        $data = Arr::only(parent::toArray($request), [
            'inventory_id', 'project', 'material', 'measurement_unit',
            'current_stock', 'minimum_stock', 'stock_status',
        ]);
        // Preserve unavailable values instead of formatting them as confirmed zero.
        if ($this->current_stock === null) {
            $data['current_stock'] = null;
        }
        if ($this->minimum_stock_level === null) {
            $data['minimum_stock'] = null;
        }

        return $data;
    }
}
