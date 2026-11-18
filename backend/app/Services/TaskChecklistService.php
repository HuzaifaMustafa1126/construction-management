<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskChecklistService
{
    public function create(Task $task, array $attributes): TaskChecklistItem
    {
        return DB::transaction(function () use ($task, $attributes): TaskChecklistItem {
            $items = $this->lockTask($task)->checklistItems();
            $item = $items->make($attributes);
            $item->position = (int) $items->max('position') + 1;
            $item->save();

            return $item;
        }, 3);
    }

    public function update(Task $task, TaskChecklistItem $item, array $attributes): TaskChecklistItem
    {
        return DB::transaction(function () use ($task, $item, $attributes): TaskChecklistItem {
            $item = $this->lockTask($task)->checklistItems()->findOrFail($item->id);
            $item->update($attributes);

            return $item;
        }, 3);
    }

    public function delete(Task $task, TaskChecklistItem $item): void
    {
        DB::transaction(function () use ($task, $item): void {
            $task = $this->lockTask($task);
            $task->checklistItems()->findOrFail($item->id)->delete();
            $this->setPositions($task->checklistItems()->get());
        }, 3);
    }

    public function reorder(Task $task, array $itemIds): Collection
    {
        return DB::transaction(function () use ($task, $itemIds): Collection {
            $items = $this->lockTask($task)->checklistItems()->get()->keyBy('id');
            $ids = collect($itemIds)->map(fn ($id) => (int) $id);

            if ($ids->count() !== $items->count() || $ids->unique()->count() !== $ids->count() || $ids->diff($items->keys())->isNotEmpty()) {
                throw ValidationException::withMessages(['item_ids' => 'Supply each checklist item ID for this task exactly once.']);
            }

            $ordered = $ids->map(fn (int $id) => $items->get($id));
            $this->setPositions($ordered);

            return $ordered;
        }, 3);
    }

    private function lockTask(Task $task): Task
    {
        return Task::query()->whereHas('project')->lockForUpdate()->findOrFail($task->id);
    }

    private function setPositions(Collection $items): void
    {
        foreach ($items as $index => $item) {
            $position = $index + 1;
            if ($item->position !== $position) {
                $item->position = $position;
                $item->save();
            }
        }
    }
}
