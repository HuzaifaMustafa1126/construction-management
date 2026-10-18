<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'members' => ['present', 'array', 'max:500'],
            'members.*.user_id' => ['required', 'integer', 'distinct'],
            'members.*.assignment_type' => ['required', Rule::in(Project::ASSIGNMENT_TYPES)],
            'company_id' => ['prohibited'], 'project_id' => ['prohibited'],
        ];
    }
}
