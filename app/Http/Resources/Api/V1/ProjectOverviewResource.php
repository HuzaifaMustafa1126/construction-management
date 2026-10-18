<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'code' => $this->code,
            'status' => $this->status, 'progress_percentage' => $this->progress_percentage,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'expected_end_date' => $this->expected_end_date?->format('Y-m-d'),
            'actual_end_date' => $this->actual_end_date?->format('Y-m-d'),
            'location' => ['name' => $this->location, 'city' => $this->city, 'state' => $this->state, 'country' => $this->country],
            'team_summary' => [
                'total' => $this->members->count(),
                'members' => $this->members->where('pivot.assignment_type', 'member')->count(),
                'supervisors' => $this->members->where('pivot.assignment_type', 'supervisor')->count(),
                'clients' => $this->members->where('pivot.assignment_type', 'client')->count(),
            ],
            'budget' => $this->when($request->user()?->can('viewBudget', $this->resource), $this->budget),
            'currency' => $this->when($request->user()?->can('viewBudget', $this->resource), $this->currency),
        ];
    }
}
