<?php

namespace App\Http\Requests\Api\V1;

class SaveTaskCommentRequest extends TaskCommentRequest
{
    public function rules(): array
    {
        return ['comment' => ['required', 'string', 'max:5000']] + parent::rules();
    }
}
