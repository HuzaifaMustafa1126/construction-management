<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\SitePhotoRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreSitePhotoRequest extends FormRequest
{
    use SitePhotoRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->metadataRules(), $this->protectedRules(), ['photo' => $this->imageRules(), 'photos' => ['prohibited']]);
    }
}
