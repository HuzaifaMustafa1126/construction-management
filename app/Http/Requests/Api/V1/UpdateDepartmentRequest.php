<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = app(TenantContext::class)->companyId();
        $id = $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:125', $this->uniqueWithinCompany('name', $companyId, $id)],
            'code' => ['sometimes', 'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', $this->uniqueWithinCompany('code', $companyId, $id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'company_id' => ['prohibited'], 'status' => ['prohibited'], 'created_by' => ['prohibited'],
        ];
    }

    private function uniqueWithinCompany(string $column, int $companyId, int|string|null $except): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($column, $companyId, $except): void {
            $exists = \DB::table('departments')->where('company_id', $companyId)->whereNull('deleted_at')
                ->where('id', '!=', $except)->whereRaw("lower({$column}) = ?", [mb_strtolower((string) $value)])->exists();
            if ($exists) {
                $fail("The {$attribute} has already been taken.");
            }
        };
    }
}
