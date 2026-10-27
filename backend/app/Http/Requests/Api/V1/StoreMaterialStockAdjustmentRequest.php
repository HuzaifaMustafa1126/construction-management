<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\MaterialStockAdjustmentRules;
use App\Models\MaterialStockAdjustmentItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMaterialStockAdjustmentRequest extends FormRequest
{
    use MaterialStockAdjustmentRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->contentRules(), $this->protectedRules());
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('items', []) as $index => $item) {
                $type = $item['adjustment_type'] ?? null;
                if (($item['reason_code'] ?? null) === 'other' && blank($item['notes'] ?? null)) {
                    $validator->errors()->add("items.{$index}.notes", 'Notes are required when the reason code is other.');
                }
                if (in_array($type, MaterialStockAdjustmentItem::CORRECTION_TYPES, true)) {
                    if (blank($item['correction_explanation'] ?? null)) {
                        $validator->errors()->add("items.{$index}.correction_explanation", 'A correction explanation is required.');
                    }
                    if (blank($item['reference_inventory_transaction_id'] ?? null)) {
                        $validator->errors()->add("items.{$index}.reference_inventory_transaction_id", 'An original inventory transaction is required for corrections.');
                    }
                } elseif (filled($item['reference_inventory_transaction_id'] ?? null)) {
                    $validator->errors()->add("items.{$index}.reference_inventory_transaction_id", 'An original transaction may only be supplied for corrections.');
                }
            }
        }];
    }
}
