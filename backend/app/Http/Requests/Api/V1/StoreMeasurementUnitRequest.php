<?php

namespace App\Http\Requests\Api\V1;

use App\Models\MeasurementUnit;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeasurementUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name')), 'symbol' => trim((string) $this->input('symbol'))]);
    }

    public function rules(): array
    {
        return array_merge($this->editableRules(), $this->protectedRules());
    }

    protected function editableRules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'symbol' => ['required', 'string', 'max:30', $this->uniqueSymbol()], 'unit_type' => ['required', Rule::in(MeasurementUnit::TYPES)], 'decimal_precision' => ['required', 'integer', 'min:0', 'max:6'], 'is_active' => ['sometimes', 'boolean']];
    }

    protected function protectedRules(): array
    {
        return ['company_id' => ['prohibited'], 'is_system' => ['prohibited']];
    }

    protected function uniqueSymbol(?int $ignore = null): Closure
    {
        return function (string $a, mixed $v, Closure $fail) use ($ignore): void {
            $q = MeasurementUnit::query()->where('company_id', app(TenantContext::class)->companyId())->whereRaw('LOWER(TRIM(symbol)) = ?', [mb_strtolower(trim((string) $v))]);
            if ($ignore) {
                $q->whereKeyNot($ignore);
            } if ($q->exists()) {
                $fail('The symbol has already been taken.');
            }
        };
    }
}
