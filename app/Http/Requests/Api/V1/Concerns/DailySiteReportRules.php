<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Models\DailySiteReportWorker;
use Illuminate\Validation\Rule;

trait DailySiteReportRules
{
    protected function contentRules(bool $updating = false): array
    {
        $sometimes = $updating ? ['sometimes'] : [];

        return [
            'report_date' => [...$sometimes, 'required', 'date'],
            'title' => [...$sometimes, 'required', 'string', 'max:255'],
            'work_completed' => [...$sometimes, 'required', 'string', 'max:10000'],
            'work_in_progress' => [...$sometimes, 'nullable', 'string', 'max:10000'],
            'issues' => [...$sometimes, 'nullable', 'string', 'max:10000'],
            'delays' => [...$sometimes, 'nullable', 'string', 'max:10000'],
            'delay_reason' => [...$sometimes, 'nullable', 'string', 'max:5000'],
            'weather_conditions' => [...$sometimes, 'nullable', 'string', 'max:255'],
            'general_notes' => [...$sometimes, 'nullable', 'string', 'max:10000'],
            'progress_percentage' => [...$sometimes, 'nullable', 'integer', 'between:0,100'],
            'workers' => [$updating ? 'sometimes' : 'present', 'array', 'max:50'],
            'workers.*.worker_type' => ['required', 'string', 'distinct', Rule::in(DailySiteReportWorker::TYPES)],
            'workers.*.workers_present' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function protectedRules(): array
    {
        return [
            'company_id' => ['prohibited'], 'project_id' => ['prohibited'],
            'total_workers_present' => ['prohibited'], 'status' => ['prohibited'],
            'is_client_visible' => ['prohibited'], 'created_by' => ['prohibited'],
            'submitted_by' => ['prohibited'], 'submitted_at' => ['prohibited'],
            'reviewed_by' => ['prohibited'], 'reviewed_at' => ['prohibited'],
            'review_notes' => ['prohibited'],
        ];
    }
}
