<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AssignUserDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['present', 'nullable', 'integer'],
            'company_id' => ['prohibited'], 'role' => ['prohibited'], 'status' => ['prohibited'],
        ];
    }
}
