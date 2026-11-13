<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ListInventoryCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('inventory.view') ?? false) && ! $this->user()->hasRole('client');
    }

    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
