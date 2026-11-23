<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use LogicException;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;

class TaskActivityService
{
    private const LOG_NAME = 'tasks';

    public const FIELDS = [
        'title', 'description', 'status', 'assigned_to', 'progress_percentage',
        'priority', 'start_date', 'due_date', 'phase_id', 'milestone_id',
        'estimated_hours', 'actual_hours', 'position', 'completed_at', 'deleted_at',
    ];

    public const DESCRIPTIONS = [
        'task.created' => 'Task created',
        'task.updated' => 'Task updated',
        'task.status_changed' => 'Task status changed',
        'task.assigned' => 'Task assigned',
        'task.reassigned' => 'Task reassigned',
        'task.unassigned' => 'Task unassigned',
        'task.progress_changed' => 'Task progress changed',
        'task.priority_changed' => 'Task priority changed',
        'task.schedule_changed' => 'Task schedule changed',
        'task.deleted' => 'Task deleted',
    ];

    public function __construct(private readonly TenantContext $tenant) {}

    public function snapshot(Task $task): array
    {
        return Arr::only($task->attributesToArray(), self::FIELDS) + array_fill_keys(self::FIELDS, null);
    }

    public function recordSave(Task $task, User $actor, ?array $before): void
    {
        $after = $this->snapshot($task);

        if ($before === null) {
            $this->record($task, $actor, 'task.created', [], array_filter($after, fn ($value) => $value !== null));

            return;
        }

        $changed = array_filter($after, fn ($value, $field) => $value !== $before[$field], ARRAY_FILTER_USE_BOTH);
        if ($changed === []) {
            return;
        }

        $old = array_intersect_key($before, $changed);
        $this->record($task, $actor, $this->updateEvent($old, $changed), $old, $changed);
    }

    public function recordDeletion(Task $task, User $actor): void
    {
        $this->record($task, $actor, 'task.deleted', ['deleted_at' => null], [
            'deleted_at' => $task->deleted_at->toISOString(),
        ]);
    }

    public function paginate(Task $task, int $perPage): LengthAwarePaginator
    {
        $companyId = $this->tenant->companyId();
        $activities = Activity::query()
            ->inLog(self::LOG_NAME)
            ->forSubject($task)
            ->whereIn('event', array_keys(self::DESCRIPTIONS))
            ->where('properties->company_id', $companyId)
            ->where('properties->project_id', $task->project_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $userType = (new User)->getMorphClass();
        $actorIds = $activities->getCollection()->where('causer_type', $userType)->pluck('causer_id')->filter()->unique();
        $actors = User::query()->where('company_id', $companyId)->whereKey($actorIds->all())->get(['id', 'name'])->keyBy('id');
        foreach ($activities as $activity) {
            $activity->setRelation('causer', $activity->causer_type === $userType ? $actors->get($activity->causer_id) : null);
        }

        return $activities;
    }

    private function updateEvent(array $before, array $after): string
    {
        // One event per save. The most specific event labels the full change set.
        return match (true) {
            array_key_exists('status', $after) => 'task.status_changed',
            array_key_exists('assigned_to', $after) => match (true) {
                $after['assigned_to'] === null => 'task.unassigned',
                $before['assigned_to'] === null => 'task.assigned',
                default => 'task.reassigned',
            },
            array_key_exists('progress_percentage', $after) => 'task.progress_changed',
            array_key_exists('priority', $after) => 'task.priority_changed',
            array_key_exists('start_date', $after), array_key_exists('due_date', $after) => 'task.schedule_changed',
            default => 'task.updated',
        };
    }

    private function record(Task $task, User $actor, string $event, array $before, array $after): void
    {
        $activity = activity(self::LOG_NAME)
            ->causedBy($actor)
            ->performedOn($task)
            ->event($event)
            ->withProperties([
                'company_id' => $this->tenant->companyId(),
                'project_id' => (int) $task->project_id,
                'task_id' => $task->id,
                'changes' => ['before' => $before, 'after' => $after, 'fields' => array_keys($after)],
            ])
            ->tap(function (Activity $activity) use ($task): void {
                if ($activity->getConnection() !== $task->getConnection()) {
                    throw new LogicException('Task activity must use the task transaction connection.');
                }
            })
            ->log(self::DESCRIPTIONS[$event]);

        if ($activity !== null && ! $activity->exists) {
            throw new RuntimeException('Task activity could not be persisted.');
        }
    }
}
