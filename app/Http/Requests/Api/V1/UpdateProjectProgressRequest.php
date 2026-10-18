<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['progress_percentage' => ['required', 'integer', 'between:0,100'], 'company_id' => ['prohibited']];
    }
}
