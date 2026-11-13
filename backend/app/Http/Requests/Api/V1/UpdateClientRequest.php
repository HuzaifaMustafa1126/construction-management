<?php

namespace App\Http\Requests\Api\V1;

class UpdateClientRequest extends StoreClientRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fields = collect($this->fields())->map(fn (array $rules) => ['sometimes', ...$rules])->all();

        return array_merge($fields, [
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'company_id' => ['prohibited'],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
        ]);
    }
}
