<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialConsumptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'consumption_number' => $this->consumption_number, 'consumption_date' => $this->consumption_date?->format('Y-m-d'), 'status' => $this->status, 'purpose' => $this->purpose, 'work_location' => $this->work_location, 'notes' => $this->notes, 'project' => $this->whenLoaded('project', fn () => ['id' => $this->project->id, 'name' => $this->project->name, 'code' => $this->project->code]), 'items' => MaterialConsumptionItemResource::collection($this->whenLoaded('items')), 'item_count' => $this->when(isset($this->items_count), $this->items_count), 'recorder' => $this->whenLoaded('recorder', fn () => $this->recorder ? ['id' => $this->recorder->id, 'name' => $this->recorder->name] : null), 'creator' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]), 'posted_at' => $this->posted_at, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
