<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Project;
use App\Models\ProjectPhase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectPhaseRequest extends FormRequest
{
    public Project $project;

    public ?ProjectPhase $phase = null;

    public function authorize(): bool
    {
        $this->project = Project::query()->accessibleTo($this->user())->findOrFail($this->route('id'));

        if ($this->route('phaseId') !== null) {
            $this->phase = $this->project->phases()->findOrFail($this->route('phaseId'));
        }

        return Gate::allows(in_array($this->method(), ['GET', 'HEAD'], true) ? 'viewAny' : 'manage', [ProjectPhase::class, $this->project]);
    }

    public function rules(): array
    {
        return $this->protectedFields();
    }

    protected function phaseRules(bool $creating): array
    {
        return [
            'name' => [...($creating ? [] : ['sometimes']), 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:16000'],
            'start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'required', Rule::in(ProjectPhase::STATUSES)],
        ];
    }

    protected function protectedFields(): array
    {
        return [
            'company_id' => ['missing'],
            'project_id' => ['missing'],
            'progress_percentage' => ['missing'],
            'sequence' => ['missing'],
        ];
    }
}
