<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDailySiteReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['prohibited'], 'submitted_by' => ['prohibited'], 'submitted_at' => ['prohibited'],
            'reviewed_by' => ['prohibited'], 'reviewed_at' => ['prohibited'], 'review_notes' => ['prohibited'],
        ];
    }
}
