<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ProjectInventory;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['minimum_stock_level' => ['required', 'decimal:0,4', 'min:0', $this->validPrecision()], 'material_id' => ['prohibited'], 'company_id' => ['prohibited'], 'project_id' => ['prohibited'], 'created_by' => ['prohibited'], 'current_stock' => ['prohibited'], 'quantity' => ['prohibited']];
    }

    private function validPrecision(): Closure
    {
        return function (string $a, mixed $v, Closure $fail): void {
            $i = ProjectInventory::query()->forCompany(app(TenantContext::class)->companyId())->with('material.measurementUnit')->find($this->route('inventoryId'));
            $parts = explode('.', (string) $v, 2);
            if ($i && isset($parts[1]) && strlen($parts[1]) > $i->material->measurementUnit->decimal_precision) {
                $fail('The minimum stock level exceeds the measurement unit precision.');
            }
        };
    }
}
