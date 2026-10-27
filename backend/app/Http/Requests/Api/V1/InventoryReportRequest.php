<?php

namespace App\Http\Requests\Api\V1;

use App\Models\InventoryTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'material_id' => ['nullable', 'integer'],
            'material_category_id' => ['nullable', 'integer'],
            'transaction_type' => ['nullable', Rule::in(InventoryTransaction::TYPES)],
            'direction' => ['nullable', Rule::in(InventoryTransaction::DIRECTIONS)],
            'source_document' => ['nullable', Rule::in(['opening_stock', 'material_delivery', 'material_consumption', 'stock_adjustment'])],
            'actor_id' => ['nullable', 'integer'],
            'reason_code' => ['nullable', 'string', 'max:50'],
            'sort' => ['nullable', Rule::in(['movement_date', 'created_at', 'material_name', 'current_stock'])],
            'direction_sort' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'company_id' => ['prohibited'],
        ];
    }
}
