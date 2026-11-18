<?php

namespace App\Http\Requests\Api\V1;

class ReorderTaskChecklistRequest extends TaskChecklistRequest
{
    public function authorize(): bool
    {
        $this->resolveResources();

        return $this->allows('viewAny') && $this->allows('update');
    }

    public function rules(): array
    {
        return [
            'item_ids' => ['present', 'array', 'list'],
            'item_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ] + parent::rules();
    }
}
