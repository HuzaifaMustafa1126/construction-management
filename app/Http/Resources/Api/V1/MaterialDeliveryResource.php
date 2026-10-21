<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialDeliveryResource extends JsonResource
{
    public function toArray(Request $r): array
    {
        return ['id' => $this->id, 'delivery_number' => $this->delivery_number, 'supplier_invoice_number' => $this->supplier_invoice_number, 'delivery_date' => $this->delivery_date?->format('Y-m-d'), 'received_at' => $this->received_at, 'status' => $this->status, 'vehicle_number' => $this->vehicle_number, 'delivery_note' => $this->delivery_note, 'supplier' => $this->whenLoaded('supplier', fn () => new SupplierResource($this->supplier)), 'project' => $this->whenLoaded('project', fn () => ['id' => $this->project->id, 'name' => $this->project->name, 'code' => $this->project->code]), 'items' => MaterialDeliveryItemResource::collection($this->whenLoaded('items')), 'item_count' => $this->when(isset($this->items_count), $this->items_count), 'receiver' => $this->whenLoaded('receiver', fn () => $this->receiver ? ['id' => $this->receiver->id, 'name' => $this->receiver->name] : null), 'creator' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]), 'posted_at' => $this->posted_at, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
