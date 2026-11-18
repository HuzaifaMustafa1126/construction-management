<?php

namespace App\Http\Requests\Api\V1;

class StoreTaskChecklistItemRequest extends TaskChecklistRequest
{
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:255'], 'is_done' => ['missing']] + parent::rules();
    }
}
