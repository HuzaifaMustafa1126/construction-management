<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProjectInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'material_category_id' => ['nullable', 'integer'], 'material_status' => ['nullable', 'boolean'], 'low_stock' => ['nullable', 'boolean'], 'stock_status' => ['nullable', Rule::in(['good', 'low_stock', 'out_of_stock'])], 'sort' => ['nullable', Rule::in(['material_name', 'created_at'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}
