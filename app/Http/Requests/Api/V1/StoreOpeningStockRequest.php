<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ProjectInventory;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreOpeningStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['quantity' => ['required', 'decimal:0,4', $this->positive(), $this->validPrecision()], 'transaction_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:5000'], 'transaction_type' => ['prohibited'], 'direction' => ['prohibited'], 'company_id' => ['prohibited'], 'project_id' => ['prohibited'], 'material_id' => ['prohibited'], 'project_inventory_id' => ['prohibited'], 'created_by' => ['prohibited']];
    }

    private function inventory(): ?ProjectInventory
    {
        return ProjectInventory::query()->forCompany(app(TenantContext::class)->companyId())->with('material.measurementUnit')->find($this->route('inventoryId'));
    }

    private function positive(): Closure
    {
        return function (string $a, mixed $v, Closure $fail): void {
            if (! preg_match('/^\d+(?:\.\d+)?$/', (string) $v) || preg_match('/^0+(?:\.0+)?$/', (string) $v)) {
                $fail('The quantity must be greater than zero.');
            }
        };
    }

    private function validPrecision(): Closure
    {
        return function (string $a, mixed $v, Closure $fail): void {
            $i = $this->inventory();
            $parts = explode('.', (string) $v, 2);
            if ($i && isset($parts[1]) && strlen($parts[1]) > $i->material->measurementUnit->decimal_precision) {
                $fail('The quantity exceeds the measurement unit precision.');
            }
        };
    }
}
