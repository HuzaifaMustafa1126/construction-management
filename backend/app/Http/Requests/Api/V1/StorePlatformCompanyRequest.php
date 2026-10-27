<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePlatformCompanyRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isPlatformAdmin() === true; }

    protected function prepareForValidation(): void
    {
        $company = (array) $this->input('company', []);
        $admin = (array) $this->input('admin', []);
        foreach ([&$company, &$admin] as &$values) {
            if (isset($values['email'])) $values['email'] = mb_strtolower(trim((string) $values['email']));
        }
        $this->merge(compact('company', 'admin'));
    }

    public function rules(): array
    {
        return [
            'company' => ['required', 'array:name,email,phone,address'],
            'company.name' => ['required', 'string', 'max:255'],
            'company.email' => ['required', 'email', 'max:255', 'unique:companies,email'],
            'company.phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\- ]{6,28}$/'],
            'company.address' => ['nullable', 'string', 'max:2000'],
            'admin' => ['required', 'array:first_name,last_name,email,phone'],
            'admin.first_name' => ['required', 'string', 'max:100'],
            'admin.last_name' => ['required', 'string', 'max:100'],
            'admin.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin.phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\- ]{6,28}$/'],
        ];
    }
}
