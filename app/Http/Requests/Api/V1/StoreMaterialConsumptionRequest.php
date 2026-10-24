<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\MaterialConsumptionRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaterialConsumptionRequest extends FormRequest
{
    use MaterialConsumptionRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->contentRules(), $this->protectedRules());
    }
}
