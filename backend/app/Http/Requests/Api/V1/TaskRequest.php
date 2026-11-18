<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    public ?Project $project = null;

    public ?Task $task = null;

    public function authorize(): bool
    {
        $this->resolveResources();

        return $this->allows($this->isMethod('DELETE') ? 'delete' : 'viewAny');
    }

    protected function resolveResources(): void
    {
        if ($this->route('taskId') !== null) {
            $this->task = Task::query()->accessibleTo($this->user())->with('project')->findOrFail($this->route('taskId'));
            $this->project = $this->task->project ?? abort(404);
        } elseif ($this->route('id') !== null) {
            $this->project = Project::query()->accessibleTo($this->user())->findOrFail($this->route('id'));
        }
    }

    public function rules(): array
    {
        return $this->protectedFields();
    }

    protected function allows(string $ability): bool
    {
        return Gate::allows($ability, $this->project ? [Task::class, $this->project] : Task::class);
    }

    protected function taskRules(bool $creating): array
    {
        return [
            'title' => [...($creating ? [] : ['sometimes']), 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:16000'],
            'phase_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'milestone_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'start_date' => ['sometimes', 'nullable', ...$this->dateRules()],
            'due_date' => ['sometimes', 'nullable', ...$this->dateRules()],
            'priority' => ['sometimes', 'required', Rule::in(Task::PRIORITIES)],
            'status' => ['sometimes', 'required', Rule::in(Task::STATUSES)],
            'position' => $creating ? ['missing'] : ['sometimes', 'required', 'integer', 'between:1,4294967294'],
            'progress_percentage' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'between:0,100'],
            'estimated_hours' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'between:0,999999.99'],
            'actual_hours' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'between:0,999999.99'],
        ] + $this->protectedFields();
    }

    protected function dateRules(): array
    {
        return ['date_format:Y-m-d', 'after_or_equal:1000-01-01'];
    }

    private function protectedFields(): array
    {
        return [
            'company_id' => ['missing'],
            'project_id' => ['missing'],
            'created_by' => ['missing'],
            'completed_at' => ['missing'],
            'deleted_at' => ['missing'],
        ];
    }
}
