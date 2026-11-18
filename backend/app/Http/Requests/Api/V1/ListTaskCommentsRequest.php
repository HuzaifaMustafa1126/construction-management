<?php

namespace App\Http\Requests\Api\V1;

class ListTaskCommentsRequest extends TaskCommentRequest
{
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'required', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'required', 'integer', 'min:1'],
        ] + parent::rules();
    }
}
