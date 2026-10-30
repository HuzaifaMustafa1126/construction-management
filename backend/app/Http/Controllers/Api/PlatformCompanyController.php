<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePlatformCompanyRequest;
use App\Http\Requests\Api\V1\UpdatePlatformCompanyRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyRoleInitializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlatformCompanyController extends Controller
{
    public function __construct(private readonly CompanyRoleInitializer $roles) {}

    public function dashboard(): JsonResponse
    {
        return response()->json(['data' => [
            'total_companies' => Company::count(),
            'active_companies' => Company::where('status', Company::STATUS_ACTIVE)->count(),
            'suspended_companies' => Company::where('status', Company::STATUS_SUSPENDED)->count(),
            'recent_companies' => Company::latest()->limit(5)->get(['id','name','email','status','created_at']),
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['search' => ['nullable','string','max:255'], 'page' => ['nullable','integer','min:1'], 'per_page' => ['nullable','integer','min:1','max:100']]);
        $companies = Company::query()->with(['users' => fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'company_admin'))->select('id','company_id','first_name','last_name','email','phone','status')])
            ->when($validated['search'] ?? null, fn ($q, $s) => $q->where(fn ($x) => $x->where('name','like',"%$s%")->orWhere('email','like',"%$s%")))
            ->latest()->paginate($validated['per_page'] ?? 15);
        return response()->json(['data' => $companies->items(), 'meta' => ['current_page'=>$companies->currentPage(),'last_page'=>$companies->lastPage(),'per_page'=>$companies->perPage(),'total'=>$companies->total()]]);
    }

    public function show(Company $company): JsonResponse
    {
        return response()->json(['data' => $company->load(['users' => fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name','company_admin'))->select('id','company_id','first_name','last_name','email','phone','status','created_at')])]);
    }

    public function store(StorePlatformCompanyRequest $request): JsonResponse
    {
        [$company, $admin] = DB::transaction(function () use ($request) {
            $c = $request->validated('company'); $a = $request->validated('admin');
            $company = Company::create(array_merge($c, ['slug'=>Str::slug($c['name']).'-'.Str::lower((string) Str::ulid()),'currency'=>'PKR','timezone'=>'Asia/Karachi','subscription_status'=>Company::SUBSCRIPTION_STATUS_TRIAL,'status'=>Company::STATUS_ACTIVE,'onboarding_status'=>Company::ONBOARDING_COMPLETE,'email_verification_required'=>false]));
            $admin = new User(array_merge($a, ['name'=>trim($a['first_name'].' '.$a['last_name']),'status'=>User::STATUS_ACTIVE]));
            $admin->company_id = $company->id; $admin->save(); $this->roles->initialize($company, $admin);
            activity('platform')->causedBy($request->user())->performedOn($company)->event('platform.company_created')->withProperties(['company_id'=>$company->id,'administrator_id'=>$admin->id])->log('platform.company_created');
            return [$company, $admin];
        });
        Password::sendResetLink(['email' => $admin->email]);
        return response()->json(['message'=>'Company created. A password setup link was sent to its administrator.','data'=>$company->load('users')], 201);
    }

    public function update(UpdatePlatformCompanyRequest $request, Company $company): JsonResponse
    {
        $before=$company->only(['name','email','phone','address']); $company->update($request->validated());
        activity('platform')->causedBy($request->user())->performedOn($company)->event('platform.company_updated')->withProperties(['before'=>$before,'after'=>$company->only(array_keys($request->validated()))])->log('platform.company_updated');
        return response()->json(['message'=>'Company updated.','data'=>$company]);
    }

    public function status(Request $request, Company $company): JsonResponse
    {
        $validated=$request->validate(['status'=>['required',Rule::in([Company::STATUS_ACTIVE,Company::STATUS_SUSPENDED])]]); $before=$company->status;
        $company->update(['status'=>$validated['status']]);
        if ($validated['status'] === Company::STATUS_SUSPENDED) $company->users()->each(fn (User $u) => $u->tokens()->delete());
        activity('platform')->causedBy($request->user())->performedOn($company)->event('platform.company_status_changed')->withProperties(['before'=>$before,'after'=>$company->status])->log('platform.company_status_changed');
        return response()->json(['message'=>'Company status updated.','data'=>$company]);
    }

    public function administratorStatus(Request $request, Company $company, User $user): JsonResponse
    {
        abort_unless($user->company_id === $company->id, 404);
        $isAdmin = DB::table('model_has_roles')->join('roles','roles.id','=','model_has_roles.role_id')->where('model_has_roles.model_id',$user->id)->where('model_has_roles.model_type',User::class)->where('roles.company_id',$company->id)->where('roles.name','company_admin')->exists();
        abort_unless($isAdmin, 404);
        $validated=$request->validate(['status'=>['required',Rule::in(['Active','Inactive','Suspended'])]]);
        if ($validated['status'] !== 'Active') {
            $activeAdmins=User::query()->where('company_id',$company->id)->where('status',User::STATUS_ACTIVE)->whereHas('roles',fn($q)=>$q->where('name','company_admin'))->count();
            abort_if($activeAdmins <= 1 && $user->isActive(), 422, 'The last active company administrator cannot be disabled.');
        }
        $before=$user->status; $user->update(['status'=>$validated['status']]);
        if ($validated['status'] !== 'Active') $user->tokens()->delete();
        activity('platform')->causedBy($request->user())->performedOn($user)->event('platform.company_administrator_status_changed')->withProperties(['company_id'=>$company->id,'before'=>$before,'after'=>$user->status])->log('platform.company_administrator_status_changed');
        return response()->json(['message'=>'Administrator status updated.','data'=>$user]);
    }
}
