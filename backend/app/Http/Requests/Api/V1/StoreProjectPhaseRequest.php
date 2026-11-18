<?php

namespace App\Http\Requests\Api\V1;

class StoreProjectPhaseRequest extends ProjectPhaseRequest
{
    public function rules(): array
    {
        return $this->phaseRules(true) + array_diff_key($this->protectedFields(), ['sequence' => true]);
    }
}
