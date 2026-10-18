<?php

namespace App\Http\Requests\Api\V1;

class UpdateProfileRequest extends UpdateUserRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['email'] = ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,'.$this->user()->id];

        return $rules;
    }
}
