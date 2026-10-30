<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Models\Company;
use App\Models\User;
use App\Notifications\AccountPasswordReset;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class PasswordRecoveryController extends Controller
{
    private const REQUEST_MESSAGE = 'If an eligible account matches that email, a password reset link has been sent.';

    private const INVALID_RESET_MESSAGE = 'This password reset link is invalid or expired.';

    public function request(ForgotPasswordRequest $request): JsonResponse
    {
        if (config('mail.default') === 'log') {
            return response()->json(['message' => self::REQUEST_MESSAGE]);
        }

        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user && $this->canRecover($user)) {
            try {
                $broker = Password::broker();
                $tokens = $broker->getRepository();

                if (! $tokens->recentlyCreatedToken($user)) {
                    $user->notify(new AccountPasswordReset($broker->createToken($user)));
                }
            } catch (Throwable) {
                // Keep delivery failures indistinguishable from unknown or ineligible accounts.
            }
        }

        return response()->json(['message' => self::REQUEST_MESSAGE]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! $this->canRecover($user)) {
            return response()->json(['message' => self::INVALID_RESET_MESSAGE], 422);
        }

        $passwordChanged = false;
        $status = Password::reset(
            $credentials,
            function (User $resetUser, string $password) use (&$passwordChanged): void {
                if (! $this->canRecover($resetUser)) {
                    return;
                }

                $resetUser->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                $resetUser->tokens()->delete();
                $passwordChanged = true;

                event(new PasswordReset($resetUser));
            },
        );

        if ($status !== Password::PASSWORD_RESET || ! $passwordChanged) {
            return response()->json(['message' => self::INVALID_RESET_MESSAGE], 422);
        }

        return response()->json(['message' => 'Password reset successfully. You can now sign in.']);
    }

    private function canRecover(User $user): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->isPlatformAdmin()) {
            return true;
        }

        if (! $user->company_id) {
            return false;
        }

        $company = Company::query()->find($user->company_id);

        if (! $company?->isOperational()) {
            return false;
        }

        return ! $company->email_verification_required || $user->hasVerifiedEmail();
    }
}
