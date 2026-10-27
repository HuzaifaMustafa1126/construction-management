<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialStockAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'adjustment_number' => $this->adjustment_number, 'adjustment_date' => $this->adjustment_date?->format('Y-m-d'), 'status' => $this->status, 'reason' => $this->reason, 'notes' => $this->notes, 'project' => $this->whenLoaded('project', fn () => ['id' => $this->project->id, 'name' => $this->project->name, 'code' => $this->project->code]), 'items' => MaterialStockAdjustmentItemResource::collection($this->whenLoaded('items')), 'item_count' => $this->when(isset($this->items_count), $this->items_count), 'creator' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]), 'poster' => $this->whenLoaded('poster', fn () => $this->poster ? ['id' => $this->poster->id, 'name' => $this->poster->name] : null), 'canceller' => $this->whenLoaded('canceller', fn () => $this->canceller ? ['id' => $this->canceller->id, 'name' => $this->canceller->name] : null), 'posted_at' => $this->posted_at, 'cancelled_at' => $this->cancelled_at, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
