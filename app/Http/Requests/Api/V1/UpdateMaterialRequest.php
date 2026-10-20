<?php

namespace App\Http\Requests\Api\V1;

class UpdateMaterialRequest extends StoreMaterialRequest
{
    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (['name', 'code'] as $key) {
            if ($this->has($key)) {
                $data[$key] = trim((string) $this->input($key));
            }
        } $this->merge($data);
    }

    public function rules(): array
    {
        $rules = $this->editableRules();
        foreach ($rules as $key => &$rule) {
            array_unshift($rule, 'sometimes');
            if ($key === 'code') {
                $rule[count($rule) - 1] = $this->uniqueCode((int) $this->route('id'));
            }
        }

        return array_merge($rules, $this->protectedRules());
    }
}
