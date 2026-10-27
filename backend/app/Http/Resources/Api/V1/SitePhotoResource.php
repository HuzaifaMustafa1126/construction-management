<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SitePhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $management = ! ($request->user()?->hasRole('client') ?? true);

        return [
            'id' => $this->id, 'project_id' => $this->project_id, 'daily_site_report_id' => $this->daily_site_report_id,
            'caption' => $this->caption, 'description' => $this->description, 'photo_date' => $this->photo_date?->format('Y-m-d'),
            'time_category' => $this->time_category, 'uploader' => $this->whenLoaded('uploader', fn () => ['id' => $this->uploader->id, 'name' => $this->uploader->name]),
            'approval_status' => $this->when($management, $this->approval_status), 'is_client_visible' => $this->when($management, $this->is_client_visible),
            'reviewed_by' => $this->when($management, $this->reviewed_by), 'reviewed_at' => $this->when($management, $this->reviewed_at),
            'review_notes' => $this->when($management, $this->review_notes), 'file_size' => $this->file_size, 'mime_type' => $this->mime_type,
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
        ];
    }
}
