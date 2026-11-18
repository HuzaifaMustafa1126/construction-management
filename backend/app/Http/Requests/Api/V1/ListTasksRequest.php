<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Task;
use Illuminate\Validation\Rule;

class ListTasksRequest extends TaskRequest
{
    public function rules(): array
    {
        return [
            'project_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'phase_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'milestone_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'assigned_to' => ['sometimes', 'required', 'integer', 'min:1'],
            'status' => ['sometimes', 'required', Rule::in(Task::STATUSES)],
            'priority' => ['sometimes', 'required', Rule::in(Task::PRIORITIES)],
            'date_from' => ['nullable', ...$this->dateRules()],
            'date_to' => [
                'nullable', ...$this->dateRules(),
                Rule::when($this->filled('date_from'), 'after_or_equal:date_from'),
            ],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['position', 'due_date', 'start_date', 'created_at', 'title'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ] + parent::rules();
    }
}
