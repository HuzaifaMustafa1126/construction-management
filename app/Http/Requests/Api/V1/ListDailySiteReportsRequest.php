<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DailySiteReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDailySiteReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'], 'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in(DailySiteReport::STATUSES)],
            'sort' => ['nullable', Rule::in(['report_date', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
