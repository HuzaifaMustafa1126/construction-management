<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->trashed() || ! $user->isActive() || ! $user->isPlatformAdmin()) {
            return response()->json(['message' => 'Platform administrator access is required.'], 403);
        }

        return $next($request);
    }
}
