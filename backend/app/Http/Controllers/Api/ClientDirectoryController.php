<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListClientsRequest;
use App\Models\Client;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClientDirectoryController extends Controller
{
    public function __invoke(ListClientsRequest $request, TenantContext $tenant): JsonResponse
    {
        abort_unless($request->user()->can('clients.view'), 403);
        $companyId = $tenant->companyId();
        $hasLink = Schema::hasColumn('clients', 'user_id');
        $canViewAccounts = $request->user()->can('users.view');
        $contacts = Client::query()->where('clients.company_id', $companyId)
            ->select(['clients.id', 'clients.name', 'clients.company_name', 'clients.email', 'clients.phone', 'clients.created_at'])
            ->selectRaw("'business_client' as type");
        if ($hasLink && $canViewAccounts) {
            $contacts->leftJoin('users as account', fn ($join) => $join->on('account.id', '=', 'clients.user_id')
                ->where('account.company_id', $companyId))
                ->addSelect(['clients.user_id', 'account.email as account_email', 'account.status as account_status', 'account.deleted_at as account_deleted_at']);
        } else {
            $contacts->selectRaw(($hasLink ? 'clients.user_id' : 'NULL as user_id').', NULL as account_email, NULL as account_status, NULL as account_deleted_at');
        }
        $collection = $contacts->toBase();
        if ($canViewAccounts) {
            $accounts = User::query()->where('users.company_id', $companyId)
                ->whereHas('roles', fn ($roles) => $roles->where('roles.company_id', $companyId)->where('roles.guard_name', 'web')
                    ->where('roles.name', 'client')->where('model_has_roles.company_id', $companyId))
                ->select(['users.id', 'users.name'])->selectRaw('NULL as company_name')
                ->addSelect(['users.email', 'users.phone', 'users.created_at'])
                ->selectRaw("'account_only' as type, users.id as user_id, users.email as account_email, users.status as account_status, users.deleted_at as account_deleted_at");
            if ($hasLink) {
                $accounts->whereNotExists(fn ($linked) => $linked->selectRaw('1')->from('clients')
                    ->whereColumn('clients.user_id', 'users.id')->where('clients.company_id', $companyId)->whereNull('clients.deleted_at'));
            }
            $collection->unionAll($accounts->toBase());
        }
        $validated = $request->validated();
        $query = DB::query()->fromSub($collection, 'directory');
        $search = $validated['search'] ?? null;
        if ($search !== null && $search !== '') {
            $query->where(fn ($matching) => $matching->where('name', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
        }
        $page = $query->orderBy($validated['sort'] ?? 'name', $validated['direction'] ?? 'asc')
            ->orderBy('type')->orderBy('id')->paginate($validated['per_page'] ?? 15)->withQueryString();
        $rows = collect($page->items())->map(fn ($row) => [
            'id' => $row->id, 'type' => $row->type, 'name' => $row->name, 'company_name' => $row->company_name,
            'email' => $row->email, 'phone' => $row->phone,
            'has_login_account' => $row->type === 'business_client' && $hasLink ? $row->user_id !== null : null,
            ...($canViewAccounts ? ['account' => $row->user_id !== null && $row->account_email !== null
                ? ['id' => $row->user_id, 'email' => $row->account_email, 'status' => $row->account_status, 'deleted' => $row->account_deleted_at !== null] : null] : []),
        ]);

        return response()->json(['data' => $rows, 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(),
            'total' => $page->total(), 'from' => $page->firstItem(), 'to' => $page->lastItem(),
        ]]);
    }
}
