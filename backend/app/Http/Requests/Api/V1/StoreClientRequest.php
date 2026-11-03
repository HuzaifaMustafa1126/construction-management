<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->fields(), [
            'name' => ['required', 'string', 'max:200'],
            'company_id' => ['prohibited'],
        ]);
    }

    protected function fields(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'id' => ['prohibited'], 'created_at' => ['prohibited'], 'updated_at' => ['prohibited'],
            'deleted_at' => ['prohibited'], 'projects' => ['prohibited'],
        ];
    }
}
