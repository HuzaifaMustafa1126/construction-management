<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Milestone;
use Illuminate\Validation\Rule;

class ListMilestonesRequest extends MilestoneRequest
{
    public function rules(): array
    {
        return [
            'phase_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'status' => ['sometimes', 'required', Rule::in(Milestone::STATUSES)],
        ] + parent::rules();
    }
}
