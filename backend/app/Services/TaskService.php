<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function create(Project $project, User $creator, array $attributes): Task
    {
        return DB::transaction(function () use ($project, $creator, $attributes): Task {
            $project = $this->lockProject($project);
            $task = $project->tasks()->make();
            $task->created_by = $creator->id;
            $task->position = (int) $project->tasks()->max('position') + 1;
            abort_if($task->position > 4294967295, 409, 'The task position limit for this project was reached.');

            return $this->save($project, $task, Arr::except($attributes, ['position']));
        }, 3);
    }

    public function update(Project $project, Task $task, array $attributes): Task
    {
        return DB::transaction(function () use ($project, $task, $attributes): Task {
            $project = $this->lockProject($project);
            $task = $project->tasks()->findOrFail($task->id);

            return $this->save($project, $task, $attributes);
        }, 3);
    }

    public function delete(Project $project, Task $task): void
    {
        DB::transaction(function () use ($project, $task): void {
            $this->lockProject($project)->tasks()->findOrFail($task->id)->delete();
        }, 3);
    }

    private function lockProject(Project $project): Project
    {
        return Project::query()->lockForUpdate()->findOrFail($project->id);
    }

    private function save(Project $project, Task $task, array $attributes): Task
    {
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

        $task->save();

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
