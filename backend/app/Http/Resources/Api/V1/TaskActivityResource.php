<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Services\TaskActivityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class TaskActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $before = Arr::only((array) $this->getExtraProperty('changes.before', []), TaskActivityService::FIELDS);
        $after = Arr::only((array) $this->getExtraProperty('changes.after', []), TaskActivityService::FIELDS);

        return [
            'id' => $this->id,
            'event' => $this->event,
            'description' => TaskActivityService::DESCRIPTIONS[$this->event],
            'actor_id' => $this->causer_type === (new User)->getMorphClass() && $this->causer_id !== null
                ? (int) $this->causer_id
                : null,
            'actor_name' => $this->causer?->name,
            'changed_fields' => array_keys($after),
            'old_values' => (object) $before,
            'new_values' => (object) $after,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
