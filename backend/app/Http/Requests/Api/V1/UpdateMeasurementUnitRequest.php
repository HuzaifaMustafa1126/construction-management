<?php

namespace App\Http\Requests\Api\V1;

class UpdateMeasurementUnitRequest extends StoreMeasurementUnitRequest
{
    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (['name', 'symbol'] as $key) {
            if ($this->has($key)) {
                $data[$key] = trim((string) $this->input($key));
            }
        } $this->merge($data);
    }

    public function rules(): array
    {
        $rules = $this->editableRules();
        foreach ($rules as $key => &$rule) {
            $rule[0] = 'sometimes';
            if ($key === 'symbol') {
                $rule[count($rule) - 1] = $this->uniqueSymbol((int) $this->route('id'));
            }
        }

        return array_merge($rules, $this->protectedRules());
    }
}
