<?php

namespace App\Http\Requests\Api\V1;

class UpdateSupplierRequest extends StoreSupplierRequest
{
    public function rules(): array
    {
        $r = parent::rules();
        foreach ($r as $k => &$v) {
            if (! in_array($k, ['company_id', 'created_by'], true)) {
                array_unshift($v, 'sometimes');
            }
        }

        return $r;
    }
}
