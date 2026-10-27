<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = app(TenantContext::class)->companyId();

        return [
            'name' => ['required', 'string', 'max:125', $this->uniqueWithinCompany('name', $companyId)],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', $this->uniqueWithinCompany('code', $companyId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(['Active', 'Inactive'])],
            'company_id' => ['prohibited'], 'created_by' => ['prohibited'],
        ];
    }

    private function uniqueWithinCompany(string $column, int $companyId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($column, $companyId): void {
            $exists = \DB::table('departments')->where('company_id', $companyId)->whereNull('deleted_at')
                ->whereRaw("lower({$column}) = ?", [mb_strtolower((string) $value)])->exists();
            if ($exists) {
                $fail("The {$attribute} has already been taken.");
            }
        };
    }
}
