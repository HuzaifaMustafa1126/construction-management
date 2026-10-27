<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterCompanyRequest;
use App\Http\Requests\Api\V1\ResendCompanyVerificationRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyRoleInitializer;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyRegistrationController extends Controller
{
    public function __construct(private CompanyRoleInitializer $roles) {}

    public function register(RegisterCompanyRequest $request): JsonResponse
    {
        $validated = $request->validated();
        try {
            [$company, $administrator] = DB::transaction(function () use ($validated) {
                $companyData = $validated['company'];
                $adminData = $validated['admin'];
                $company = Company::create([
                    'name' => $companyData['name'],
                    'slug' => Str::slug($companyData['name']).'-'.Str::lower((string) Str::ulid()),
                    'email' => $companyData['email'],
                    'phone' => $companyData['phone'],
                    'address' => $companyData['address'] ?? null,
                    'currency' => 'PKR',
                    'timezone' => 'Asia/Karachi',
                    'subscription_status' => Company::SUBSCRIPTION_STATUS_TRIAL,
                    'status' => Company::STATUS_PENDING_VERIFICATION,
                    'onboarding_status' => Company::ONBOARDING_PENDING_VERIFICATION,
                    'email_verification_required' => true,
                ]);
                $administrator = new User([
                    'name' => trim($adminData['first_name'].' '.$adminData['last_name']),
                    'first_name' => $adminData['first_name'],
                    'last_name' => $adminData['last_name'],
                    'email' => $adminData['email'],
                    'phone' => $adminData['phone'] ?? null,
                    'password' => $adminData['password'],
                    'status' => User::STATUS_ACTIVE,
                ]);
                $administrator->company_id = $company->id;
                $administrator->save();
                $this->roles->initialize($company, $administrator);
                $this->log($company, $administrator, 'company.registered');
                $this->log($administrator, $administrator, 'company.first_administrator_created', $company);
                $this->log($administrator, $administrator, 'company.initial_role_assigned', $company);

                return [$company, $administrator];
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '19'], true)) {
                return response()->json(['message' => 'The registration details are already in use.', 'errors' => ['registration' => ['The company or administrator is already registered.']]], 422);
            }
            throw $exception;
        }

        $administrator->sendEmailVerificationNotification();

        return response()->json(['message' => 'Company registered successfully. Please verify your email.', 'data' => ['company' => ['id' => $company->id, 'name' => $company->name, 'status' => $company->status], 'admin' => ['id' => $administrator->id, 'first_name' => $administrator->first_name, 'last_name' => $administrator->last_name, 'email' => $administrator->email, 'email_verified' => false], 'onboarding_status' => $company->onboarding_status]], 201);
    }

    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return response()->json(['message' => 'The verification link is invalid or expired.'], 403);
        }
        $user = User::query()->with('company')->find($id);
        if (! $user || ! hash_equals($hash, sha1($user->getEmailForVerification())) || ! $user->company) {
            return response()->json(['message' => 'The verification link is invalid or expired.'], 403);
        }
        $activated = DB::transaction(function () use ($user) {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $company = Company::query()->whereKey($lockedUser->company_id)->lockForUpdate()->firstOrFail();
            if (! $lockedUser->hasVerifiedEmail()) {
                $lockedUser->markEmailAsVerified();
                $this->log($lockedUser, $lockedUser, 'company.administrator_email_verified', $company);
            }
            if ($company->status === Company::STATUS_PENDING_VERIFICATION && $company->onboarding_status === Company::ONBOARDING_PENDING_VERIFICATION) {
                $company->forceFill(['status' => Company::STATUS_ACTIVE, 'onboarding_status' => Company::ONBOARDING_COMPLETE])->save();
                $this->log($company, $lockedUser, 'company.activated');

                return true;
            }

            return false;
        });

        return response()->json(['message' => $activated ? 'Email verified and company activated successfully.' : 'Email verification is already complete or company activation is unavailable.']);
    }

    public function resend(ResendCompanyVerificationRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->with('company')->first();
        if ($user && ! $user->hasVerifiedEmail() && $user->company?->status === Company::STATUS_PENDING_VERIFICATION && $user->company->onboarding_status === Company::ONBOARDING_PENDING_VERIFICATION) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'If the registration is pending verification, a new verification email has been sent.']);
    }

    private function log($subject, User $actor, string $event, ?Company $company = null): void
    {
        $company ??= $subject instanceof Company ? $subject : $actor->company;
        activity('company_onboarding')->causedBy($actor)->performedOn($subject)->event($event)->withProperties(['company_id' => $company->id, 'administrator_id' => $actor->id, 'timestamp' => now()->toIso8601String()])->log($event);
    }
}
