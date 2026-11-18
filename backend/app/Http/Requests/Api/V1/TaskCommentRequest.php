<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Support\Facades\Gate;

class TaskCommentRequest extends TaskRequest
{
    public ?TaskComment $comment = null;

    public function authorize(): bool
    {
        $this->resolveResources();

        if (! $this->allows('viewAny')) {
            return false;
        }

        return match ($this->method()) {
            'GET', 'HEAD' => true,
            'POST' => $this->allows('participate'),
            default => Gate::allows('manageComment', [Task::class, $this->project, $this->comment]),
        };
    }

    protected function resolveResources(): void
    {
        parent::resolveResources();

        if ($this->route('commentId') !== null) {
            $this->comment = $this->task->comments()->findOrFail($this->route('commentId'));
        }
    }

    public function rules(): array
    {
        return [
            'task_id' => ['missing'],
            'user_id' => ['missing'],
        ] + parent::rules();
    }
}
