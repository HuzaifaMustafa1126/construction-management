<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class DailySiteReportPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project' => ['id' => $this->project->id, 'name' => $this->project->name, 'code' => $this->project->code],
            'title' => $this->title,
            'report_date' => $this->report_date?->format('Y-m-d'),
            'creator' => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null,
            'summary' => Str::limit($this->work_completed ?: ($this->work_in_progress ?: ''), 280),
            'total_workers_present' => $this->total_workers_present,
            'status' => $this->status,
        ];
    }
}
