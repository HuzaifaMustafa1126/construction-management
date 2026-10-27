<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
    // Login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $this->loginDenied();
        }

        if (! $user->isActive()) {
            return $this->loginDenied();
        }

        if ($user->isPlatformAdmin()) {
            return response()->json([
                'message' => 'Login successful',
                'user' => $user,
                'token' => $user->createToken('platform_auth_token', ['platform'])->plainTextToken,
            ]);
        }

        $company = $user->company_id
            ? Company::query()->find($user->company_id)
            : null;

        if (! $company) {
            return $this->loginDenied();
        }

        if ($company->email_verification_required && ! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email verification is required before login.'], 403);
        }

        if (! $company->isOperational()) {
            return $this->loginDenied();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    private function loginDenied()
    {
        return response()->json([
            'message' => 'Invalid credentials or account unavailable.',
        ], 401);
    }

    // Get logged-in user
    public function me(Request $request)
    {
        $user = $request->user();
        if (! $user->isActive() || ($user->company_id === null && ! $user->isPlatformAdmin())) {
            return response()->json(['message' => 'Account access is unavailable.'], 403);
        }
        if (! $user->isPlatformAdmin()) {
            $company = Company::query()->find($user->company_id);
            if (! $company?->isOperational()) {
                return response()->json(['message' => 'Company access is suspended.'], 403);
            }
            $previousTeamId = getPermissionsTeamId();
            try {
                setPermissionsTeamId($company->id);
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                $user->unsetRelation('roles')->unsetRelation('permissions');
                $identity = array_merge($user->toArray(), [
                    'roles' => $user->getRoleNames()->sort()->values()->all(),
                    'effective_permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
                    'company' => ['id' => $company->id, 'name' => $company->name, 'status' => $company->status],
                ]);
            } finally {
                setPermissionsTeamId($previousTeamId);
                $user->unsetRelation('roles')->unsetRelation('permissions');
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        } else {
            $identity = $user->toArray();
        }
        return response()->json([
            'user' => $identity,
        ]);
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }
}
