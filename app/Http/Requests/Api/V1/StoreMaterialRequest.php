<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MeasurementUnit;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name')), 'code' => trim((string) $this->input('code'))]);
    }

    public function rules(): array
    {
        return array_merge($this->editableRules(), $this->protectedRules());
    }

    protected function editableRules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $this->uniqueCode()], 'material_category_id' => ['required', 'integer', $this->validCategory()], 'measurement_unit_id' => ['required', 'integer', $this->validUnit()], 'description' => ['nullable', 'string', 'max:5000'], 'specification' => ['nullable', 'string', 'max:5000'], 'brand' => ['nullable', 'string', 'max:255'], 'is_active' => ['sometimes', 'boolean']];
    }

    protected function protectedRules(): array
    {
        return ['company_id' => ['prohibited'], 'created_by' => ['prohibited']];
    }

    protected function uniqueCode(?int $ignore = null): Closure
    {
        return function (string $a, mixed $v, Closure $fail) use ($ignore): void {
            $q = Material::query()->forCompany(app(TenantContext::class)->companyId())->whereRaw('LOWER(TRIM(code)) = ?', [mb_strtolower(trim((string) $v))]);
            if ($ignore) {
                $q->whereKeyNot($ignore);
            } if ($q->exists()) {
                $fail('The code has already been taken.');
            }
        };
    }

    protected function validCategory(): Closure
    {
        return function (string $a, mixed $v, Closure $fail): void {
            if (! MaterialCategory::query()->forCompany(app(TenantContext::class)->companyId())->whereKey($v)->where('is_active', true)->exists()) {
                $fail('The selected material category is invalid or inactive.');
            }
        };
    }

    protected function validUnit(): Closure
    {
        return function (string $a, mixed $v, Closure $fail): void {
            if (! MeasurementUnit::query()->availableTo(app(TenantContext::class)->companyId())->whereKey($v)->where('is_active', true)->exists()) {
                $fail('The selected measurement unit is invalid or inactive.');
            }
        };
    }
}
