<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Material;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['material_id' => ['required', 'integer', $this->validMaterial()], 'minimum_stock_level' => ['sometimes', 'decimal:0,4', 'min:0', $this->validPrecision()], 'company_id' => ['prohibited'], 'project_id' => ['prohibited'], 'created_by' => ['prohibited'], 'current_stock' => ['prohibited']];
    }

    private function material(): ?Material
    {
        return Material::query()->forCompany(app(TenantContext::class)->companyId())->with(['category', 'measurementUnit'])->find($this->input('material_id'));
    }

    private function validMaterial(): Closure
    {
        return function (string $a, mixed $v, Closure $fail): void {
            $m = $this->material();
            if (! $m || ! $m->is_active || ! $m->category?->is_active || ! $m->measurementUnit?->is_active) {
                $fail('The selected material is invalid or inactive.');
            }
        };
    }

    private function validPrecision(): Closure
    {
        return function (string $a, mixed $v, Closure $fail): void {
            $m = $this->material();
            if ($m && ! $this->precisionFits((string) $v, $m->measurementUnit->decimal_precision)) {
                $fail('The minimum stock level exceeds the measurement unit precision.');
            }
        };
    }

    private function precisionFits(string $value, int $precision): bool
    {
        $parts = explode('.', $value, 2);

        return ! isset($parts[1]) || strlen($parts[1]) <= $precision;
    }
}
