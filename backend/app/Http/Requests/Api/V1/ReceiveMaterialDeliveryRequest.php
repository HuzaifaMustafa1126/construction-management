<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveMaterialDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['received_at' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:5000'], 'status' => ['prohibited'], 'company_id' => ['prohibited'], 'project_id' => ['prohibited'], 'transaction_type' => ['prohibited'], 'direction' => ['prohibited']];
    }
}
