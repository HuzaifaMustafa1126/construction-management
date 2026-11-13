<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Client;
use App\Services\ClientService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class StoreClientLoginAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $companyId = app(TenantContext::class)->companyId();
        app(ClientService::class)->authorizeProvisioning($this->user(), $companyId, 'clients.update');
        $client = Client::query()->forCompany($companyId)->findOrFail($this->route('id'));
        abort_unless($this->user()->can('update', $client), 403);
        if ($client->user_id !== null) {
            throw new ConflictHttpException('This client already has a linked login account.');
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
            'password_confirmation' => ['required', 'string'],
            ...StoreClientRequest::protectedFields(),
        ];
    }

    public function messages(): array
    {
        return ['email.unique' => 'This email is not available for a new login account.'];
    }
}
