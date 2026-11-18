<?php

namespace App\Http\Requests\Api\V1;

class UpdateProjectPhaseRequest extends ProjectPhaseRequest
{
    public function rules(): array
    {
        return $this->phaseRules(false) + $this->protectedFields();
    }
}
