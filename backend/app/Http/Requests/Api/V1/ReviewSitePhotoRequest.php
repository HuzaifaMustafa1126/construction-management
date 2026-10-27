<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewSitePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['approve', 'reject'])], 'review_notes' => ['nullable', 'string', 'max:5000', Rule::requiredIf(fn () => $this->input('action') === 'reject')]];
    }
}
