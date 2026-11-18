<?php

namespace App\Http\Requests\Api\V1;

class ReorderProjectPhasesRequest extends ProjectPhaseRequest
{
    public function rules(): array
    {
        return [
            'phase_ids' => ['present', 'array', 'list'],
            'phase_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ] + $this->protectedFields();
    }
}
