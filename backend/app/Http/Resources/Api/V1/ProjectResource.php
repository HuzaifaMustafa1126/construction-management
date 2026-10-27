<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'location' => $this->location,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'expected_end_date' => $this->expected_end_date?->format('Y-m-d'),
            'actual_end_date' => $this->actual_end_date?->format('Y-m-d'),
            'budget' => $this->when($request->user()?->can('viewBudget', $this->resource), $this->budget),
            'currency' => $this->when($request->user()?->can('viewBudget', $this->resource), $this->currency),
            'progress_percentage' => $this->progress_percentage,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'team_summary' => $this->whenLoaded('members', fn () => [
                'total' => $this->members->count(),
                'members' => $this->members->where('pivot.assignment_type', 'member')->count(),
                'supervisors' => $this->members->where('pivot.assignment_type', 'supervisor')->count(),
                'clients' => $this->members->where('pivot.assignment_type', 'client')->count(),
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
