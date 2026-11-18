<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectPhase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProjectPhaseService
{
    public const DEFAULT_PHASES = [
        'Planning & Design',
        'Site Preparation',
        'Excavation & Earthworks',
        'Foundation Works',
        'Structural Works',
        'Masonry & Blockwork',
        'Mechanical, Electrical & Plumbing (MEP)',
        'Plastering & Finishing',
        'Fixtures & Fit-Out',
        'Testing, Inspection & Handover',
    ];

    public function create(Project $project, array $attributes): ProjectPhase
    {
        return DB::transaction(function () use ($project, $attributes): ProjectPhase {
            $project = $this->lockProject($project);
            $phase = $project->phases()->make($attributes);
            $phase->sequence = (int) $project->phases()->max('sequence') + 1;
            $this->validateDates($phase);
            $phase->save();

            return $phase->refresh();
        }, 3);
    }

    public function update(Project $project, ProjectPhase $phase, array $attributes): ProjectPhase
    {
        return DB::transaction(function () use ($project, $phase, $attributes): ProjectPhase {
            $phase = $this->lockProject($project)->phases()->findOrFail($phase->id);
            $phase->fill($attributes);
            $this->validateDates($phase);
            $phase->save();

            return $phase;
        }, 3);
    }

    public function delete(Project $project, ProjectPhase $phase): void
    {
        try {
            DB::transaction(function () use ($project, $phase): void {
                $project = $this->lockProject($project);
                $phase = $project->phases()->findOrFail($phase->id);

                foreach (['milestones', 'tasks'] as $table) {
                    if (Schema::hasColumn($table, 'phase_id') && DB::table($table)->where('phase_id', $phase->id)->exists()) {
                        abort(409, 'A phase with dependent milestones or tasks cannot be deleted.');
                    }
                }

                $phase->delete();
                $this->setSequence($project->phases()->get());
            }, 3);
        } catch (QueryException $exception) {
            // Future milestone and task foreign keys must restrict phase deletion.
            if ((int) ($exception->errorInfo[1] ?? 0) === 1451) {
                abort(409, 'A phase with dependent records cannot be deleted.');
            }

            throw $exception;
        }
    }

    public function reorder(Project $project, array $ids): Collection
    {
        return DB::transaction(function () use ($project, $ids): Collection {
            $project = $this->lockProject($project);
            $phases = $project->phases()->get()->keyBy('id');
            $ids = collect($ids)->map(fn ($id) => (int) $id);

            if ($ids->count() !== $phases->count() || $ids->unique()->count() !== $ids->count() || $ids->diff($phases->keys())->isNotEmpty()) {
                throw ValidationException::withMessages(['phase_ids' => 'Supply each phase ID for this project exactly once.']);
            }

            $ordered = $ids->map(fn (int $id) => $phases->get($id));
            $this->setSequence($ordered);

            return $ordered;
        }, 3);
    }

    public function applyDefaults(Project $project): Collection
    {
        return DB::transaction(function () use ($project): Collection {
            $project = $this->lockProject($project);
            abort_if($project->phases()->exists(), 409, 'Default phases can only be applied to a project without phases.');

            foreach (self::DEFAULT_PHASES as $index => $name) {
                $phase = $project->phases()->make(['name' => $name]);
                $phase->sequence = $index + 1;
                $phase->save();
            }

            return $project->phases()->get();
        }, 3);
    }

    private function lockProject(Project $project): Project
    {
        return Project::query()->lockForUpdate()->findOrFail($project->id);
    }

    private function validateDates(ProjectPhase $phase): void
    {
        if ($phase->start_date && $phase->end_date && $phase->end_date->lt($phase->start_date)) {
            throw ValidationException::withMessages(['end_date' => 'The end date must be on or after the start date.']);
        }
    }

    private function setSequence(Collection $phases): void
    {
        foreach ($phases as $index => $phase) {
            $sequence = $index + 1;
            if ($phase->sequence !== $sequence) {
                $phase->sequence = $sequence;
                $phase->save();
            }
        }
    }
}
