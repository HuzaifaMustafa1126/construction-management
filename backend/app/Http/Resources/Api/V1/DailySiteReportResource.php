<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailySiteReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $client = $request->user()?->hasRole('client') ?? false;

        return [
            'id' => $this->id,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id, 'name' => $this->project->name, 'code' => $this->project->code,
            ]),
            'report_date' => $this->report_date?->format('Y-m-d'),
            'title' => $this->title,
            'work_completed' => $this->work_completed,
            'work_in_progress' => $this->work_in_progress,
            'issues' => $this->issues,
            'delays' => $this->delays,
            'delay_reason' => $this->delay_reason,
            'weather_conditions' => $this->weather_conditions,
            'general_notes' => $this->general_notes,
            'workers' => DailySiteReportWorkerResource::collection($this->whenLoaded('workers')),
            'total_workers_present' => $this->total_workers_present,
            'progress_percentage' => $this->progress_percentage,
            'status' => $this->status,
            'is_client_visible' => $this->is_client_visible,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id, 'name' => $this->creator->name,
            ]),
            'submitted_by' => $this->submitted_by,
            'submitted_at' => $this->submitted_at,
            'reviewed_by' => $this->when(! $client, $this->reviewed_by),
            'reviewed_at' => $this->when(! $client, $this->reviewed_at),
            'review_notes' => $this->when(! $client, $this->review_notes),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
