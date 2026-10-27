<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyContext
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->denied('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->company_id) {
            return $this->denied('A company assignment is required.', Response::HTTP_FORBIDDEN);
        }

        $company = Company::query()->find($user->company_id);

        if (! $company) {
            return $this->denied('A valid company assignment is required.', Response::HTTP_FORBIDDEN);
        }

        if ($company->status !== Company::STATUS_ACTIVE) {
            return $this->denied('Company access is suspended.', Response::HTTP_FORBIDDEN);
        }

        $this->tenantContext->establish($company);
        setPermissionsTeamId($company->getKey());
        $user->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $next($request);
        } finally {
            setPermissionsTeamId(null);
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $this->tenantContext->clear();
        }
    }

    private function denied(string $message, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], $status);
    }
}
