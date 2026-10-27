<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateCompanyRequest;
use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function show(Request $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->can('company.view')) {
            return $this->forbidden();
        }

        return response()->json(['data' => $this->serialize($tenant->company())]);
    }

    public function update(UpdateCompanyRequest $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->can('company.update')) {
            return $this->forbidden();
        }
        $company = $tenant->company();
        $validated = $request->validated();
        $before = $company->only(array_keys($validated));
        $company->update($validated);

        activity('company')->causedBy($request->user())->performedOn($company)->event('company.profile_updated')
            ->withProperties(['company_id' => $company->id, 'changes' => [
                'before' => $before, 'after' => $company->only(array_keys($validated)),
            ]])->log('company.profile_updated');

        return response()->json(['data' => $this->serialize($company), 'message' => 'Company profile updated successfully.']);
    }

    private function serialize(Company $company): array
    {
        return $company->only([
            'id', 'name', 'email', 'phone', 'address', 'city', 'state', 'country',
            'postal_code', 'website', 'logo', 'currency', 'timezone', 'created_at', 'updated_at',
        ]);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }
}
