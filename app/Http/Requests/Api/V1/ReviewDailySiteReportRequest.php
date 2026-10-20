<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewDailySiteReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'request_changes'])],
            'review_notes' => ['nullable', 'required_if:action,request_changes', 'string', 'max:5000'],
            'status' => ['prohibited'], 'reviewed_by' => ['prohibited'], 'reviewed_at' => ['prohibited'],
        ];
    }
}
