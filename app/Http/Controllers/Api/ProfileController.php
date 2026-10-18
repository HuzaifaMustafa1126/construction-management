<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request, TenantContext $tenant): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $before = $user->only(array_keys($validated));
        $user->fill($validated);
        if (! array_key_exists('name', $validated)
            && (array_key_exists('first_name', $validated) || array_key_exists('last_name', $validated))) {
            $user->name = trim($user->first_name.' '.$user->last_name);
        }
        $user->save();

        activity('users')->causedBy($user)->performedOn($user)->event('user.profile_updated')
            ->withProperties([
                'company_id' => $tenant->companyId(), 'target_user_id' => $user->id,
                'changes' => ['before' => $before, 'after' => $user->only(array_keys($validated))],
            ])->log('user.profile_updated');

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => new UserResource($user->load(['company:id,name,slug', 'roles:id,name'])),
        ]);
    }

    public function password(ChangePasswordRequest $request, TenantContext $tenant): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        DB::transaction(function () use ($user, $validated, $tenant): void {
            $user->update(['password' => Hash::make($validated['password'])]);
            $currentTokenId = $user->currentAccessToken()?->getKey();
            $user->tokens()->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))->delete();

            activity('users')->causedBy($user)->performedOn($user)->event('user.password_changed')
                ->withProperties([
                    'company_id' => $tenant->companyId(), 'target_user_id' => $user->id,
                ])->log('user.password_changed');
        });

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
