<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = app(TenantContext::class)->companyId();

        return array_merge($this->projectFields(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:75', 'regex:/^[A-Za-z0-9_-]+$/', $this->uniqueCode($companyId)],
            'company_id' => ['prohibited'], 'currency' => ['prohibited'],
            'created_by' => ['prohibited'], 'updated_by' => ['prohibited'],
            'status' => ['prohibited'], 'progress_percentage' => ['prohibited'],
            'members' => ['prohibited'], 'team' => ['prohibited'],
        ]);
    }

    private function projectFields(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:5000'], 'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'], 'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'], 'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'], 'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'actual_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
        ];
    }

    private function uniqueCode(int $companyId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($companyId): void {
            if (DB::table('projects')->where('company_id', $companyId)->whereNull('deleted_at')
                ->whereRaw('lower(code) = ?', [mb_strtolower((string) $value)])->exists()) {
                $fail('The project code has already been taken.');
            }
        };
    }
}
