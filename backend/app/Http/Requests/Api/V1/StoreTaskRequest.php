<?php

namespace App\Http\Requests\Api\V1;

class StoreTaskRequest extends TaskRequest
{
    public function authorize(): bool
    {
        $this->resolveResources();

        return $this->allows('create')
            && ($this->input('assigned_to') === null || $this->allows('assign'))
            && ($this->input('status', 'todo') === 'todo' || $this->allows('changeStatus'));
    }

    public function rules(): array
    {
        return $this->taskRules(true);
    }
}
