<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListClientAccountsRequest;
use App\Http\Resources\Api\V1\ClientAccountResource;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class ClientAccountController extends Controller
{
    public function __invoke(ListClientAccountsRequest $request, TenantContext $tenant): JsonResponse
    {
        $companyId = $tenant->companyId();
        $query = User::query()->select(['users.id', 'users.name', 'users.email', 'users.phone', 'users.status'])
            ->where('users.company_id', $companyId)
            ->whereHas('roles', fn ($roles) => $roles->where('roles.company_id', $companyId)
                ->where('roles.name', 'client')->where('roles.guard_name', 'web')
                ->where('model_has_roles.company_id', $companyId));
        $validated = $request->validated();
        if ($search = $validated['search'] ?? null) {
            $query->where(function ($matching) use ($search): void {
                $matching->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.phone', 'like', "%{$search}%");
            });
        }
        $page = $query->orderBy('users.name')->orderBy('users.id')->paginate(15)->withQueryString();

        return response()->json([
            'data' => ClientAccountResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total(),
                'from' => $page->firstItem(), 'to' => $page->lastItem(),
            ],
        ]);
    }
}
