<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TaskCommentService
{
    public function paginate(Task $task, int $perPage): LengthAwarePaginator
    {
        return $task->comments()->paginate($perPage)->withQueryString();
    }

    public function create(Task $task, User $author, string $content): TaskComment
    {
        return DB::transaction(function () use ($task, $author, $content): TaskComment {
            $task = $this->lockTask($task);
            abort_unless((int) $author->company_id === (int) $task->company_id, 404);
            $comment = $task->comments()->make(['comment' => $content]);
            $comment->author()->associate($author);
            $comment->save();

            return $comment;
        }, 3);
    }

    public function update(Task $task, TaskComment $comment, string $content): TaskComment
    {
        return DB::transaction(function () use ($task, $comment, $content): TaskComment {
            $comment = $this->lockTask($task)->comments()->findOrFail($comment->id);
            $comment->update(['comment' => $content]);

            return $comment;
        }, 3);
    }

    public function delete(Task $task, TaskComment $comment): void
    {
        DB::transaction(function () use ($task, $comment): void {
            $this->lockTask($task)->comments()->findOrFail($comment->id)->delete();
        }, 3);
    }

    private function lockTask(Task $task): Task
    {
        return Task::query()->whereHas('project')->lockForUpdate()->findOrFail($task->id);
    }
}
