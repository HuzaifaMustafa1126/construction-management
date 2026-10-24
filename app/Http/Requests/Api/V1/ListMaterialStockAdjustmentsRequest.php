<?php

namespace App\Http\Requests\Api\V1;

use App\Models\MaterialStockAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMaterialStockAdjustmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::in(MaterialStockAdjustment::STATUSES)], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'created_by' => ['nullable', 'integer'], 'sort' => ['nullable', Rule::in(['adjustment_date', 'created_at', 'adjustment_number'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}
