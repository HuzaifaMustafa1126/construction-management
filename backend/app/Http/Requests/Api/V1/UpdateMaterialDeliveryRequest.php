<?php

namespace App\Http\Requests\Api\V1;

class UpdateMaterialDeliveryRequest extends StoreMaterialDeliveryRequest
{
    public function rules(): array
    {
        $r = $this->contentRules();
        foreach (['supplier_id', 'delivery_date', 'items'] as $k) {
            array_unshift($r[$k], 'sometimes');
        }

        return array_merge($r, $this->protectedRules());
    }
}
