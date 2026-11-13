<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DailySiteReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDailySiteReportCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('daily_reports.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(DailySiteReport::STATUSES)],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
