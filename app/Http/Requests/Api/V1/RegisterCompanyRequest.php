<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $company = (array) $this->input('company', []);
        $admin = (array) $this->input('admin', []);
        if (isset($company['email'])) {
            $company['email'] = mb_strtolower(trim((string) $company['email']));
        }
        if (isset($admin['email'])) {
            $admin['email'] = mb_strtolower(trim((string) $admin['email']));
        }
        $this->merge(['company' => $company, 'admin' => $admin]);
    }

    public function rules(): array
    {
        $protected = ['company.id', 'company.company_id', 'company.slug', 'company.status', 'company.onboarding_status', 'company.email_verification_required', 'company.subscription_status', 'company.subscription_plan_id', 'company.trial_ends_at', 'company.created_by', 'admin.id', 'admin.company_id', 'admin.role', 'admin.roles', 'admin.permissions', 'admin.is_admin', 'admin.status', 'admin.email_verified_at', 'admin.created_by'];

        return array_merge([
            'company' => ['required', 'array'],
            'company.name' => ['required', 'string', 'max:255'],
            'company.email' => ['required', 'email', 'max:255', 'unique:companies,email'],
            'company.phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\- ]{6,28}$/'],
            'company.address' => ['nullable', 'string', 'max:2000'],
            'admin' => ['required', 'array'],
            'admin.first_name' => ['required', 'string', 'max:100'],
            'admin.last_name' => ['required', 'string', 'max:100'],
            'admin.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin.phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\- ]{6,28}$/'],
            'admin.password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ], collect($protected)->mapWithKeys(fn (string $key) => [$key => ['prohibited']])->all());
    }
}
