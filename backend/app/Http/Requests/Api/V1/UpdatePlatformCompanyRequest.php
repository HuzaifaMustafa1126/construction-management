<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformCompanyRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isPlatformAdmin() === true; }
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('companies')->ignore($this->route('company'))],
            'phone' => ['sometimes', 'required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\- ]{6,28}$/'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
