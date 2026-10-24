<?php

namespace App\Http\Requests\Api\V1;

class UpdateMaterialStockAdjustmentRequest extends StoreMaterialStockAdjustmentRequest
{
    public function rules(): array
    {
        $rules = $this->contentRules();
        foreach (['adjustment_date', 'items'] as $key) {
            array_unshift($rules[$key], 'sometimes');
        }

        return array_merge($rules, $this->protectedRules());
    }
}
