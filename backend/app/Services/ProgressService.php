<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProgressService
{
    public function withLockedProject(Project $project, Closure $callback): mixed
    {
        return DB::transaction(function () use ($project, $callback): mixed {
            $lockedProject = Project::query()
                ->where('company_id', $project->company_id)
                ->lockForUpdate()
                ->findOrFail($project->id);

            return $callback($lockedProject);
        }, 3);
    }

    public function recalculateProject(Project $project, User $actor): void
    {
        $this->withLockedProject($project, function (Project $project) use ($actor): void {
            // Group active work once. The locking reads also see current rows inside an outer transaction.
            $taskTotals = $project->tasks()
                ->select('phase_id')
                ->selectRaw('COUNT(*) AS task_count, ROUND(SUM(progress_percentage) * 100, 0) AS progress_hundredths')
                ->groupBy('phase_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('phase_id');

            [$phaseTotal, $nonEmptyPhases] = $this->recalculatePhases($project, $taskTotals);
            $standalone = $taskTotals->get('');
            $units = $nonEmptyPhases + (int) ($standalone?->task_count ?? 0);

            // Legacy/no-active-work fallback: retain the stored percentage when there is no work to average.
            if ($units === 0) {
                return;
            }

            $total = $phaseTotal + (int) ($standalone?->progress_hundredths ?? 0);
            $progress = $this->roundedAverage($total, $units * 100);
            $this->saveProjectProgress($project, $progress, $actor, 'project.progress_recalculated');
        });
    }

    public function updateManualProgress(Project $project, int $progress, User $actor): Project
    {
        return $this->withLockedProject($project, function (Project $project) use ($progress, $actor): Project {
            abort_if(
                $project->tasks()->lockForUpdate()->first(['id']) !== null,
                409,
                'Project progress is calculated from tasks while the project has active tasks.',
            );

            $this->saveProjectProgress($project, $progress, $actor, 'project.progress_changed');

            return $project;
        });
    }

    private function recalculatePhases(Project $project, Collection $taskTotals): array
    {
        $total = 0;
        $nonEmpty = 0;

        foreach ($project->phases()->lockForUpdate()->get() as $phase) {
            $tasks = $taskTotals->get($phase->id);
            $hundredths = $tasks
                ? $this->roundedAverage((int) $tasks->progress_hundredths, (int) $tasks->task_count, 10000)
                : 0;
            $progress = sprintf('%d.%02d', intdiv($hundredths, 100), $hundredths % 100);

            if ($phase->progress_percentage !== $progress) {
                $phase->progress_percentage = $progress;
                $phase->save();
            }

            if ($tasks) {
                $total += $hundredths;
                $nonEmpty++;
            }
        }

        return [$total, $nonEmpty];
    }

    private function roundedAverage(int $total, int $units, int $maximum = 100): int
    {
        return max(0, min($maximum, intdiv($total + intdiv($units, 2), $units)));
    }

    private function saveProjectProgress(Project $project, int $progress, User $actor, string $event): void
    {
        $before = $project->progress_percentage;
        if ($before === $progress) {
            return;
        }

        $project->progress_percentage = $progress;
        if ($event === 'project.progress_changed') {
            $project->updated_by = $actor->id;
        }
        $project->save();

        activity('projects')->causedBy($actor)->performedOn($project)->event($event)
            ->withProperties([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'changes' => ['before' => $before, 'after' => $progress],
            ])->log($event);
    }
}
