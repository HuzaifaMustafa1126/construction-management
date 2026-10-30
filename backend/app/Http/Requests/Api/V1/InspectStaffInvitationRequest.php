<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class InspectStaffInvitationRequest extends FormRequest
{
    public function rules(): array
    {
        return ['token' => ['required', 'string', 'max:255']];
    }
}
