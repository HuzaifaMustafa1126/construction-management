<?php

namespace App\Http\Requests\Api\V1;

class UpdateTaskRequest extends TaskRequest
{
    public function authorize(): bool
    {
        $this->resolveResources();
        $assigning = $this->has('assigned_to');
        $changingStatus = $this->has('status');
        $editing = (! $assigning && ! $changingStatus) || $this->hasAny([
            'title', 'description', 'phase_id', 'milestone_id', 'start_date', 'due_date',
            'priority', 'position', 'progress_percentage', 'estimated_hours', 'actual_hours',
        ]);

        return (! $editing || $this->allows('update'))
            && (! $assigning || $this->allows('assign'))
            && (! $changingStatus || $this->allows('changeStatus'));
    }

    public function rules(): array
    {
        return $this->taskRules(false);
    }
}
