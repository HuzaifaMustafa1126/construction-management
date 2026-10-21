<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\MaterialDeliveryRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaterialDeliveryRequest extends FormRequest
{
    use MaterialDeliveryRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->contentRules(), $this->protectedRules());
    }
}
