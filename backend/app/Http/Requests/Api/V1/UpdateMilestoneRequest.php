<?php

namespace App\Http\Requests\Api\V1;

class UpdateMilestoneRequest extends MilestoneRequest
{
    public function rules(): array
    {
        return $this->milestoneRules(false);
    }
}
