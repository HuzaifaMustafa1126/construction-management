<?php

namespace App\Http\Requests\Api\V1;

class ListTaskAttachmentsRequest extends TaskAttachmentRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ] + parent::rules();
    }
}
