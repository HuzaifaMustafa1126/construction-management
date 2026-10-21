<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Models\ProjectInventory;
use App\Models\Supplier;
use App\Support\Tenancy\TenantContext;
use Closure;

trait MaterialDeliveryRules
{
    protected function contentRules(): array
    {
        return ['supplier_id' => ['required', 'integer', $this->validSupplier()], 'delivery_date' => ['required', 'date'], 'supplier_invoice_number' => ['nullable', 'string', 'max:255'], 'vehicle_number' => ['nullable', 'string', 'max:100'], 'delivery_note' => ['nullable', 'string', 'max:5000'], 'items' => ['required', 'array', 'min:1', 'max:100'], 'items.*.project_inventory_id' => ['required', 'integer', 'distinct', $this->validInventory()], 'items.*.quantity' => ['required', 'decimal:0,4', $this->positive(), $this->validPrecision()], 'items.*.notes' => ['nullable', 'string', 'max:5000']];
    }

    protected function protectedRules(): array
    {
        return collect(['company_id', 'project_id', 'delivery_number', 'status', 'received_at', 'received_by', 'created_by', 'submitted_at', 'posted_at', 'transaction_type', 'direction'])->mapWithKeys(fn ($k) => [$k => ['prohibited']])->all();
    }

    private function validSupplier(): Closure
    {
        return function (string $a, mixed $v, Closure $fail): void {
            if (! Supplier::query()->forCompany(app(TenantContext::class)->companyId())->whereKey($v)->where('is_active', true)->exists()) {
                $fail('The selected supplier is invalid or inactive.');
            }
        };
    }

    private function inventory(mixed $id): ?ProjectInventory
    {
        return ProjectInventory::query()->forCompany(app(TenantContext::class)->companyId())->where('project_id', $this->route('id'))->with('material.measurementUnit')->find($id);
    }

    private function validInventory(): Closure
    {
        return function (string $a, mixed $v, Closure $fail): void {
            if (! $this->inventory($v)) {
                $fail('The selected project inventory is invalid for this project.');
            }
        };
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
            $index = (int) explode('.', $a)[1];
            $inventory = $this->inventory($this->input("items.{$index}.project_inventory_id"));
            $p = explode('.', (string) $v, 2);
            if ($inventory && isset($p[1]) && strlen($p[1]) > $inventory->material->measurementUnit->decimal_precision) {
                $fail('The quantity exceeds the measurement unit precision.');
            }
        };
    }
}
