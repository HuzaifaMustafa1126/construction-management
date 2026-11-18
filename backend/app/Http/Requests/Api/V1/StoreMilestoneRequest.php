<?php

namespace App\Http\Requests\Api\V1;

class StoreMilestoneRequest extends MilestoneRequest
{
    public function rules(): array
    {
        return $this->milestoneRules(true);
    }
}
