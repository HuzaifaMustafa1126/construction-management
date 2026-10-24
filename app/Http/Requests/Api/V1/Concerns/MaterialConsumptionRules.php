<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Models\ProjectInventory;
use App\Support\Tenancy\TenantContext;
use Closure;

trait MaterialConsumptionRules
{
    protected function contentRules(): array
    {
        return [
            'consumption_date' => ['required', 'date'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.project_inventory_id' => ['required', 'integer', 'distinct', $this->validInventory()],
            'items.*.quantity' => ['required', 'decimal:0,4', $this->positive(), $this->validPrecision()],
            'items.*.notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function protectedRules(): array
    {
        return collect(['company_id', 'project_id', 'material_id', 'items.*.material_id', 'consumption_number', 'status', 'posted_at', 'created_by', 'recorded_by', 'transaction_type', 'direction', 'current_stock'])
            ->mapWithKeys(fn (string $key) => [$key => ['prohibited']])->all();
    }

    private function inventory(mixed $id): ?ProjectInventory
    {
        return ProjectInventory::query()->forCompany(app(TenantContext::class)->companyId())
            ->where('project_id', $this->route('id'))->with('material.measurementUnit')->find($id);
    }

    private function validInventory(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $this->inventory($value)) {
                $fail('The selected project inventory is invalid for this project.');
            }
        };
    }

    private function positive(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! preg_match('/^\d+(?:\.\d+)?$/', (string) $value) || preg_match('/^0+(?:\.0+)?$/', (string) $value)) {
                $fail('The quantity must be greater than zero.');
            }
        };
    }

    private function validPrecision(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $index = (int) explode('.', $attribute)[1];
            $inventory = $this->inventory($this->input("items.{$index}.project_inventory_id"));
            $parts = explode('.', (string) $value, 2);
            if ($inventory && isset($parts[1]) && strlen($parts[1]) > $inventory->material->measurementUnit->decimal_precision) {
                $fail('The quantity exceeds the measurement unit precision.');
            }
        };
    }
}
