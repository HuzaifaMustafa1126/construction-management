<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SetupAdministratorPasswordRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AdministratorPasswordController extends Controller
{
    public function store(SetupAdministratorPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                if (! $user->company_id || $user->isPlatformAdmin()) return;
                $company = Company::query()->find($user->company_id);
                $attributes = ['password' => $password, 'remember_token' => Str::random(60)];
                if ($company?->status === Company::STATUS_ACTIVE) $attributes['status'] = User::STATUS_ACTIVE;
                if (! $user->email_verified_at) $attributes['email_verified_at'] = now();
                $user->forceFill($attributes)->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'This password setup link is invalid or expired.'], 422);
        }

        return response()->json(['message' => 'Password set successfully. You can now sign in.']);
    }
}
