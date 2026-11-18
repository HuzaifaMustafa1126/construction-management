<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Task;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends TaskRequest
{
    public function authorize(): bool
    {
        $this->resolveResources();

        return $this->allows('changeStatus');
    }

    public function rules(): array
    {
        $rules = array_fill_keys(array_keys($this->taskRules(false)), ['missing']);
        $rules['status'] = ['required', Rule::in(Task::STATUSES)];

        return $rules;
    }
}
