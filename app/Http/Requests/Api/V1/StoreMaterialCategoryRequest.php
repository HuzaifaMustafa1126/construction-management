<?php

namespace App\Http\Requests\Api\V1;

use App\Models\MaterialCategory;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaterialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return array_merge($this->editableRules(), $this->protectedRules());
    }

    protected function editableRules(): array
    {
        return ['name' => ['required', 'string', 'max:255', $this->uniqueName()], 'description' => ['nullable', 'string', 'max:5000'], 'is_active' => ['sometimes', 'boolean']];
    }

    protected function protectedRules(): array
    {
        return ['company_id' => ['prohibited'], 'created_by' => ['prohibited']];
    }

    protected function uniqueName(?int $ignore = null): Closure
    {
        return function (string $a, mixed $v, Closure $fail) use ($ignore): void {
            $q = MaterialCategory::query()->forCompany(app(TenantContext::class)->companyId())->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim((string) $v))]);
            if ($ignore) {
                $q->whereKeyNot($ignore);
            } if ($q->exists()) {
                $fail('The name has already been taken.');
            }
        };
    }
}
