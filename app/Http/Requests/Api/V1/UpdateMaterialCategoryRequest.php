<?php

namespace App\Http\Requests\Api\V1;

class UpdateMaterialCategoryRequest extends StoreMaterialCategoryRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $rules = $this->editableRules();
        foreach ($rules as $key => &$rule) {
            array_unshift($rule, 'sometimes');
            if ($key === 'name') {
                $rule[count($rule) - 1] = $this->uniqueName((int) $this->route('id'));
            }
        }

        return array_merge($rules, $this->protectedRules());
    }
}
