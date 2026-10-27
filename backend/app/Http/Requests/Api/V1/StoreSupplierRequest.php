<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $d = [];
        foreach (['name', 'contact_person', 'phone', 'email'] as $k) {
            if ($this->has($k)) {
                $d[$k] = trim((string) $this->input($k));
            }
        }$this->merge($d);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'contact_person' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+() .-]+$/'], 'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string', 'max:5000'], 'notes' => ['nullable', 'string', 'max:5000'], 'is_active' => ['sometimes', 'boolean'], 'company_id' => ['prohibited'], 'created_by' => ['prohibited']];
    }
}
