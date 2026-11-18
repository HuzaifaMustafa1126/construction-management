<?php

namespace App\Http\Requests\Api\V1;

use App\Models\TaskChecklistItem;

class TaskChecklistRequest extends TaskRequest
{
    public ?TaskChecklistItem $item = null;

    public function authorize(): bool
    {
        $this->resolveResources();

        if (! $this->allows('viewAny')) {
            return false;
        }

        return match ($this->method()) {
            'GET', 'HEAD' => true,
            'PATCH' => $this->has('title') || ! $this->has('is_done')
                ? $this->allows('update')
                : $this->allows('toggleChecklist'),
            default => $this->allows('update'),
        };
    }

    protected function resolveResources(): void
    {
        parent::resolveResources();

        if ($this->route('itemId') !== null) {
            $this->item = $this->task->checklistItems()->findOrFail($this->route('itemId'));
        }
    }

    public function rules(): array
    {
        return [
            'task_id' => ['missing'],
            'position' => ['missing'],
        ] + parent::rules();
    }
}
