<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ClientLookupRequest;
use App\Http\Requests\Api\V1\ListClientsRequest;
use App\Http\Requests\Api\V1\StoreClientLoginAccountRequest;
use App\Http\Requests\Api\V1\StoreClientRequest;
use App\Http\Requests\Api\V1\UpdateClientRequest;
use App\Http\Resources\Api\V1\ClientResource;
use App\Models\Client;
use App\Services\ClientService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ClientController extends Controller
{
    public function __construct(private readonly ClientService $clients) {}

    public function index(ListClientsRequest $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->can('clients.view')) {
            return $this->forbidden();
        }

        $validated = $request->validated();
        $query = Client::query()->forCompany($tenant->companyId())->withCount('projects');
        $this->withAccount($query, $request, $tenant->companyId());
        if ($search = $validated['search'] ?? null) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        $page = $query->orderBy($validated['sort'] ?? 'name', $validated['direction'] ?? 'asc')
            ->paginate($validated['per_page'] ?? 15)->withQueryString();

        return response()->json(['data' => ClientResource::collection($page->items()), 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
        ]]);
    }

    public function lookup(ClientLookupRequest $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->canAny(['projects.create', 'projects.edit'])) {
            return $this->forbidden();
        }

        $validated = $request->validated();
        $query = Client::query()->forCompany($tenant->companyId())
            ->select(['id', 'name', 'company_name'])->orderBy('name');
        $search = $validated['search'] ?? null;
        $selectedId = $validated['selected_id'] ?? null;
        if ($search || $selectedId) {
            $query->where(function ($builder) use ($search, $selectedId): void {
                if ($search) {
                    $builder->where(fn ($matching) => $matching->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%"));
                }
                if ($selectedId) {
                    if ($search) {
                        $builder->orWhereKey($selectedId);
                    } else {
                        $builder->whereKey($selectedId);
                    }
                }
            });
        }

        return response()->json(['data' => $query->limit($validated['limit'] ?? 20)->get()]);
    }

    public function store(StoreClientRequest $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->can('clients.create')) {
            return $this->forbidden();
        }
        $client = $this->clients->create($request->validated(), $tenant->companyId(), $request->user());

        return response()->json(['data' => new ClientResource($this->loadClient($client, $request, $tenant->companyId())), 'message' => 'Client and login account created successfully.'], 201);
    }

    public function show(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        $client = $this->find($tenant, $id);
        if (! $client) {
            return $this->notFound();
        }
        if (! $request->user()->can('view', $client)) {
            return $this->forbidden();
        }

        return response()->json(['data' => new ClientResource($this->loadClient($client, $request, $tenant->companyId()))]);
    }

    public function update(UpdateClientRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $client = $this->find($tenant, $id);
        if (! $client) {
            return $this->notFound();
        }
        if (! $request->user()->can('update', $client)) {
            return $this->forbidden();
        }
        $client = $this->clients->update($client, $request->validated(), $request->user());

        return response()->json(['data' => new ClientResource($client->loadCount('projects')), 'message' => 'Client updated successfully.']);
    }

    public function destroy(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        $client = $this->find($tenant, $id);
        if (! $client) {
            return $this->notFound();
        }
        if (! $request->user()->can('delete', $client)) {
            return $this->forbidden();
        }
        if ($client->projects()->exists()) {
            return response()->json([
                'message' => 'The client cannot be deleted while linked to an active project.',
                'errors' => ['client' => ['Remove the client from all active projects before deleting it.']],
            ], 422);
        }
        $this->clients->delete($client, $request->user());

        return response()->json(['message' => 'Client deleted successfully.']);
    }

    public function accountOptions(Request $request, TenantContext $tenant): JsonResponse
    {
        abort_unless($request->user()->canAny(['clients.view', 'clients.create', 'clients.update']), 403);

        return response()->json(['data' => $this->clients->accountOptions($request->user(), $tenant->companyId())]);
    }

    public function createLoginAccount(StoreClientLoginAccountRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $client = $this->find($tenant, $id);
        if (! $client) {
            return $this->notFound();
        }
        abort_unless($request->user()->can('update', $client), 403);
        $client = $this->clients->createLoginAccount($client, $request->validated(), $tenant->companyId(), $request->user());

        return response()->json(['data' => new ClientResource($this->loadClient($client, $request, $tenant->companyId())),
            'message' => 'Client login account created successfully.'], 201);
    }

    private function withAccount($query, Request $request, int $companyId): void
    {
        if ($request->user()->can('users.view') && Schema::hasColumn('clients', 'user_id')) {
            $query->with(['user' => fn ($users) => $users->withTrashed()->where('company_id', $companyId)
                ->select(['id', 'email', 'status', 'deleted_at'])]);
        }
    }

    private function loadClient(Client $client, Request $request, int $companyId): Client
    {
        if ($request->user()->can('users.view') && Schema::hasColumn('clients', 'user_id')) {
            $client->load(['user' => fn ($users) => $users->withTrashed()->where('company_id', $companyId)
                ->select(['id', 'email', 'status', 'deleted_at'])]);
        }

        return $client->loadCount('projects');
    }

    private function find(TenantContext $tenant, int $id): ?Client
    {
        return Client::query()->forCompany($tenant->companyId())->find($id);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Client not found.'], 404);
    }
}
