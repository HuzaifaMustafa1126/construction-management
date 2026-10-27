<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->denied('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }

        if ($user->trashed() || ! $user->isActive()) {
            return $this->denied('Account access is unavailable.');
        }

        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        if (! $user->company_id) {
            return $this->denied('A company assignment is required.');
        }

        $company = Company::query()->find($user->company_id);

        if (! $company) {
            return $this->denied('A valid company assignment is required.');
        }

        if (! $company->isOperational()) {
            return $this->denied('Company access is suspended.');
        }

        return $next($request);
    }

    private function denied(
        string $message,
        int $status = Response::HTTP_FORBIDDEN,
    ): JsonResponse {
        return response()->json(['message' => $message], $status);
    }
}
