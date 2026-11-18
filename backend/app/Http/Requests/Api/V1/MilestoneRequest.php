<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MilestoneRequest extends FormRequest
{
    public Project $project;

    public ?Milestone $milestone = null;

    public function authorize(): bool
    {
        $this->project = Project::query()->accessibleTo($this->user())->findOrFail($this->route('id'));

        if ($this->route('milestoneId') !== null) {
            $this->milestone = $this->project->milestones()->findOrFail($this->route('milestoneId'));
        }

        $ability = in_array($this->method(), ['GET', 'HEAD'], true) ? 'viewAny' : 'manage';

        return Gate::allows($ability, [Milestone::class, $this->project]);
    }

    public function rules(): array
    {
        return $this->ownershipRules();
    }

    protected function milestoneRules(bool $creating): array
    {
        $presence = $creating ? [] : ['sometimes'];

        return [
            'name' => [...$presence, 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:16000'],
            'phase_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'due_date' => [...$presence, 'required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01'],
            'completed_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01'],
            'status' => ['sometimes', 'required', Rule::in(Milestone::STATUSES)],
        ] + $this->ownershipRules();
    }

    private function ownershipRules(): array
    {
        return [
            'company_id' => ['missing'],
            'project_id' => ['missing'],
        ];
    }
}
