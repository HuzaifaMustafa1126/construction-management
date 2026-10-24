<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Models\InventoryTransaction;
use App\Models\MaterialStockAdjustmentItem;
use App\Models\ProjectInventory;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Validation\Rule;

trait MaterialStockAdjustmentRules
{
    protected function contentRules(): array
    {
        return [
            'adjustment_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.project_inventory_id' => ['required', 'integer', 'distinct', $this->validInventory()],
            'items.*.adjustment_type' => ['required', Rule::in(MaterialStockAdjustmentItem::TYPES)],
            'items.*.quantity' => ['required', 'decimal:0,4', $this->positive(), $this->validPrecision()],
            'items.*.reason_code' => ['required', Rule::in(MaterialStockAdjustmentItem::REASON_CODES)],
            'items.*.notes' => ['nullable', 'string', 'max:5000', $this->notesRequiredForOther()],
            'items.*.correction_explanation' => ['nullable', 'string', 'max:5000', $this->correctionExplanation()],
            'items.*.reference_inventory_transaction_id' => ['nullable', 'integer', $this->validCorrectionReference()],
        ];
    }

    protected function protectedRules(): array
    {
        return collect(['company_id', 'project_id', 'material_id', 'items.*.material_id', 'adjustment_number', 'status', 'posted_by', 'posted_at', 'cancelled_by', 'cancelled_at', 'created_by', 'transaction_type', 'direction', 'stock_adjustment_item_id', 'current_stock'])
            ->mapWithKeys(fn (string $key) => [$key => ['prohibited']])->all();
    }

    private function itemIndex(string $attribute): int
    {
        return (int) explode('.', $attribute)[1];
    }

    private function inventory(mixed $id): ?ProjectInventory
    {
        return ProjectInventory::query()->forCompany(app(TenantContext::class)->companyId())->where('project_id', $this->route('id'))->with('material.measurementUnit')->find($id);
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
            $index = $this->itemIndex($attribute);
            $inventory = $this->inventory($this->input("items.{$index}.project_inventory_id"));
            $parts = explode('.', (string) $value, 2);
            if ($inventory && isset($parts[1]) && strlen($parts[1]) > $inventory->material->measurementUnit->decimal_precision) {
                $fail('The quantity exceeds the measurement unit precision.');
            }
        };
    }

    private function notesRequiredForOther(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $index = $this->itemIndex($attribute);
            if ($this->input("items.{$index}.reason_code") === 'other' && blank($value)) {
                $fail('Notes are required when the reason code is other.');
            }
        };
    }

    private function correctionExplanation(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $index = $this->itemIndex($attribute);
            if (in_array($this->input("items.{$index}.adjustment_type"), MaterialStockAdjustmentItem::CORRECTION_TYPES, true) && blank($value)) {
                $fail('A correction explanation is required.');
            }
        };
    }

    private function validCorrectionReference(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $index = $this->itemIndex($attribute);
            $type = $this->input("items.{$index}.adjustment_type");
            $isCorrection = in_array($type, MaterialStockAdjustmentItem::CORRECTION_TYPES, true);
            if ($isCorrection && blank($value)) {
                $fail('An original inventory transaction is required for corrections.');

                return;
            }
            if (! $isCorrection && filled($value)) {
                $fail('An original transaction may only be supplied for corrections.');

                return;
            }
            if (! filled($value)) {
                return;
            }
            $inventoryId = $this->input("items.{$index}.project_inventory_id");
            if (! InventoryTransaction::query()->whereKey($value)->where('company_id', app(TenantContext::class)->companyId())->where('project_id', $this->route('id'))->where('project_inventory_id', $inventoryId)->exists()) {
                $fail('The referenced inventory transaction is invalid for this project inventory.');
            }
        };
    }
}
