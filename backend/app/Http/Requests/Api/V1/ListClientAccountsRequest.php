<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ListClientAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('clients.view') && $this->user()->can('users.view');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
