<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ClientLookupRequest;
use App\Http\Requests\Api\V1\ListClientsRequest;
use App\Http\Requests\Api\V1\StoreClientRequest;
use App\Http\Requests\Api\V1\UpdateClientRequest;
use App\Http\Resources\Api\V1\ClientResource;
use App\Models\Client;
use App\Services\ClientService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json(['data' => new ClientResource($client), 'message' => 'Client created successfully.'], 201);
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

        return response()->json(['data' => new ClientResource($client->loadCount('projects'))]);
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
