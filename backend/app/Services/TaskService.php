<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(
        private readonly ProgressService $progress,
        private readonly TaskActivityService $activity,
    ) {}

    public function create(Project $project, User $creator, array $attributes): Task
    {
        return $this->progress->withLockedProject($project, function (Project $project) use ($creator, $attributes): Task {
            $task = $project->tasks()->make();
            $task->created_by = $creator->id;
            $task->position = (int) $project->tasks()->max('position') + 1;
            abort_if($task->position > 4294967295, 409, 'The task position limit for this project was reached.');

            return $this->save($project, $task, Arr::except($attributes, ['position']), $creator);
        });
    }

    public function update(Project $project, Task $task, array $attributes, User $actor): Task
    {
        return $this->progress->withLockedProject($project, function (Project $project) use ($task, $attributes, $actor): Task {
            $task = $project->tasks()->lockForUpdate()->findOrFail($task->id);

            return $this->save($project, $task, $attributes, $actor);
        });
    }

    public function delete(Project $project, Task $task, User $actor): void
    {
        $this->progress->withLockedProject($project, function (Project $project) use ($task, $actor): void {
            $task = $project->tasks()->lockForUpdate()->findOrFail($task->id);
            $deleted = $task->delete();
            $this->progress->recalculateProject($project, $actor);

            if ($deleted) {
                $this->activity->recordDeletion($task, $actor);
            }
        });
    }

    private function save(Project $project, Task $task, array $attributes, User $actor): Task
    {
        $before = $task->exists ? $this->activity->snapshot($task) : null;
        $wasCompleted = $task->status === 'completed';
        $task->fill($attributes);
        $this->validateRelationships($project, $task, array_key_exists('assigned_to', $attributes));

        if ($task->start_date && $task->due_date && $task->due_date->lt($task->start_date)) {
            throw ValidationException::withMessages(['due_date' => 'The due date must be on or after the start date.']);
        }

        if ($task->status === 'completed') {
            $task->completed_at ??= now();
            $task->progress_percentage = 100;
        } else {
            $task->completed_at = null;
            if ($wasCompleted && ! array_key_exists('progress_percentage', $attributes)) {
                $task->progress_percentage = 0;
            }
        }

        $saved = $task->save();

        if ($task->wasRecentlyCreated || $task->wasChanged(['phase_id', 'status', 'progress_percentage'])) {
            $this->progress->recalculateProject($project, $actor);
        }

        if ($saved) {
            $this->activity->recordSave($task, $actor, $before);
        }

        return $task;
    }

    private function validateRelationships(Project $project, Task $task, bool $assigning): void
    {
        if ($task->phase_id !== null && ! $project->phases()->whereKey($task->phase_id)->exists()) {
            throw ValidationException::withMessages(['phase_id' => 'The selected phase is invalid for this project.']);
        }

        if ($task->milestone_id !== null) {
            $milestone = $project->milestones()->find($task->milestone_id);
            if (! $milestone || ($task->phase_id !== null && $milestone->phase_id !== null && (int) $milestone->phase_id !== $task->phase_id)) {
                throw ValidationException::withMessages(['milestone_id' => 'The selected milestone is invalid for this project or phase.']);
            }
        }

        if ($assigning && $task->assigned_to !== null) {
            $assignee = $project->members()
                ->wherePivot('company_id', $project->company_id)
                ->wherePivotIn('assignment_type', ['member', 'supervisor'])
                ->where('users.company_id', $project->company_id)
                ->where('users.status', User::STATUS_ACTIVE)
                ->lockForUpdate()
                ->find($task->assigned_to);

            if (! $assignee || ! Project::permitsAssignmentType($assignee, $assignee->pivot->assignment_type)) {
                throw ValidationException::withMessages(['assigned_to' => 'The selected user is not eligible for assignment to this project.']);
            }
        }
    }
}
