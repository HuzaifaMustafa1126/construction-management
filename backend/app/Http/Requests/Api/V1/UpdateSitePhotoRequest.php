<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\SitePhotoRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSitePhotoRequest extends FormRequest
{
    use SitePhotoRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = $this->metadataRules();
        foreach ($rules as &$rule) {
            array_unshift($rule, 'sometimes');
        }

        return array_merge($rules, $this->protectedRules(), ['photo' => ['prohibited'], 'photos' => ['prohibited']]);
    }
}
