<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDailySiteReportVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['is_client_visible' => ['required', 'boolean'], 'company_id' => ['prohibited'], 'status' => ['prohibited']];
    }
}
