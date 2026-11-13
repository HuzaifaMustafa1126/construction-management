<?php

namespace App\Http\Requests\Api\V1;

use App\Services\ClientService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(ClientService::class)->authorizeProvisioning($this->user(), app(TenantContext::class)->companyId(), 'clients.create');

        return true;
    }

    public function rules(): array
    {
        return array_merge($this->fields(), [
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
            'password_confirmation' => ['required', 'string'],
        ]);
    }

    protected function fields(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            ...self::protectedFields(),
        ];
    }

    public static function protectedFields(): array
    {
        return array_fill_keys([
            'company_id', 'user_id', 'role', 'roles', 'role_id', 'permissions', 'status', 'is_platform_admin',
            'email_verified_at', 'id', 'created_at', 'updated_at', 'deleted_at', 'projects',
            'first_name', 'last_name', 'department', 'department_id',
        ], ['prohibited']);
    }

    public function messages(): array
    {
        return ['email.unique' => 'This email is not available for a new login account.'];
    }
}
