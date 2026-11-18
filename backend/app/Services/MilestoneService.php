<?php

namespace App\Services;

use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MilestoneService
{
    public function create(Project $project, array $attributes): Milestone
    {
        return DB::transaction(function () use ($project, $attributes): Milestone {
            $project = $this->lockProject($project);
            $milestone = $project->milestones()->make();

            return $this->save($project, $milestone, $attributes);
        }, 3);
    }

    public function update(Project $project, Milestone $milestone, array $attributes): Milestone
    {
        return DB::transaction(function () use ($project, $milestone, $attributes): Milestone {
            $project = $this->lockProject($project);
            $milestone = $project->milestones()->findOrFail($milestone->id);

            return $this->save($project, $milestone, $attributes);
        }, 3);
    }

    public function delete(Project $project, Milestone $milestone): void
    {
        try {
            DB::transaction(function () use ($project, $milestone): void {
                $milestone = $this->lockProject($project)->milestones()->findOrFail($milestone->id);

                if (Schema::hasColumn('tasks', 'milestone_id') && DB::table('tasks')->where('milestone_id', $milestone->id)->exists()) {
                    abort(409, 'A milestone with dependent tasks cannot be deleted.');
                }

                $milestone->delete();
            }, 3);
        } catch (QueryException $exception) {
            // Task foreign keys must restrict milestone deletion when tasks are introduced.
            if ((int) ($exception->errorInfo[1] ?? 0) === 1451) {
                abort(409, 'A milestone with dependent records cannot be deleted.');
            }

            throw $exception;
        }
    }

    private function lockProject(Project $project): Project
    {
        return Project::query()->lockForUpdate()->findOrFail($project->id);
    }

    private function save(Project $project, Milestone $milestone, array $attributes): Milestone
    {
        $existingCompletionDate = $milestone->status === 'completed' ? $milestone->completed_date : null;
        $milestone->fill($attributes);

        if ($milestone->phase_id !== null && ! $project->phases()->whereKey($milestone->phase_id)->exists()) {
            throw ValidationException::withMessages(['phase_id' => 'The selected phase is invalid for this project.']);
        }

        if ($milestone->exists && $milestone->isDirty('phase_id') && $milestone->phase_id !== null
            && Schema::hasTable('tasks') && $milestone->tasks()->withTrashed()
                ->whereNotNull('phase_id')->where('phase_id', '!=', $milestone->phase_id)->exists()) {
            throw ValidationException::withMessages(['phase_id' => 'The selected phase conflicts with a task linked to this milestone.']);
        }

        $requestedDate = $attributes['completed_date'] ?? null;
        if ($milestone->status === 'completed') {
            $milestone->completed_date = $requestedDate ?? $existingCompletionDate ?? today();
        } else {
            if ($requestedDate !== null) {
                throw ValidationException::withMessages(['completed_date' => 'A completion date requires completed status.']);
            }

            $milestone->completed_date = null;
        }

        $milestone->save();

        return $milestone;
    }
}
