<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SitePhotoPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project' => ['id' => $this->project->id, 'name' => $this->project->name, 'code' => $this->project->code],
            'caption' => $this->caption,
            'photo_date' => $this->photo_date?->format('Y-m-d'),
            'time_category' => $this->time_category,
        ];
    }
}
