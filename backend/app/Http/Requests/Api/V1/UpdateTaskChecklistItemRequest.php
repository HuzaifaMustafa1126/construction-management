<?php

namespace App\Http\Requests\Api\V1;

class UpdateTaskChecklistItemRequest extends TaskChecklistRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required_without:is_done', 'filled', 'string', 'max:255'],
            'is_done' => ['required_without:title', 'filled', 'boolean'],
        ] + parent::rules();
    }
}
