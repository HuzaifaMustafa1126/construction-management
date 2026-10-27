<?php

namespace App\Http\Requests\Api\V1;

class UpdateMaterialConsumptionRequest extends StoreMaterialConsumptionRequest
{
    public function rules(): array
    {
        $rules = $this->contentRules();
        foreach (['consumption_date', 'items'] as $key) {
            array_unshift($rules[$key], 'sometimes');
        }

        return array_merge($rules, $this->protectedRules());
    }
}
