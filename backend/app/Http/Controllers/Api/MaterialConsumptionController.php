<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListInventoryTransactionsRequest;
use App\Http\Requests\Api\V1\ListMaterialConsumptionsRequest;
use App\Http\Requests\Api\V1\PostMaterialConsumptionRequest;
use App\Http\Requests\Api\V1\StoreMaterialConsumptionRequest;
use App\Http\Requests\Api\V1\UpdateMaterialConsumptionRequest;
use App\Http\Resources\Api\V1\InventoryTransactionResource;
use App\Http\Resources\Api\V1\MaterialConsumptionResource;
use App\Models\InventoryTransaction;
use App\Models\MaterialConsumption;
use App\Models\MaterialConsumptionItem;
use App\Models\Project;
use App\Models\ProjectInventory;
use App\Support\Inventory\DecimalQuantity;
use App\Support\Tenancy\TenantContext;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class MaterialConsumptionController extends Controller
{
    public function index(ListMaterialConsumptionsRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->project($request, $tenant, $id);
        if (! $project) {
            return $this->missing();
        }
        if (! $request->user()->can('material_consumptions.view')) {
            return $this->denied();
        }
        $validated = $request->validated();
        $query = MaterialConsumption::query()->forCompany($tenant->companyId())->where('project_id', $project->id)
            ->with(['project:id,name,code', 'recorder:id,name', 'creator:id,name'])->withCount('items');
        if ($value = $validated['search'] ?? null) {
            $query->where('consumption_number', 'like', "%{$value}%");
        }
        if ($value = $validated['status'] ?? null) {
            $query->where('status', $value);
        }
        if ($value = $validated['date_from'] ?? null) {
            $query->whereDate('consumption_date', '>=', $value);
        }
        if ($value = $validated['date_to'] ?? null) {
            $query->whereDate('consumption_date', '<=', $value);
        }
        if ($value = $validated['recorded_by'] ?? null) {
            $query->where('recorded_by', $value);
        }
        $page = $query->orderBy($validated['sort'] ?? 'consumption_date', $validated['direction'] ?? 'desc')->paginate($validated['per_page'] ?? 15);

        return response()->json(['data' => MaterialConsumptionResource::collection($page->items()), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function store(StoreMaterialConsumptionRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->project($request, $tenant, $id);
        if (! $project) {
            return $this->missing();
        }
        if (! $request->user()->can('material_consumptions.create') || ! $request->user()->can('create', [MaterialConsumption::class, $project])) {
            return $this->denied();
        }
        $validated = $request->validated();
        $items = Arr::pull($validated, 'items');
        $consumption = DB::transaction(function () use ($request, $tenant, $project, $validated, $items) {
            $consumption = new MaterialConsumption($validated);
            $consumption->forceFill(['company_id' => $tenant->companyId(), 'project_id' => $project->id, 'consumption_number' => 'CON-'.now()->format('Ymd').'-'.Str::upper((string) Str::ulid()), 'status' => 'draft', 'created_by' => $request->user()->id]);
            $consumption->save();
            $this->replaceItems($consumption, $items);
            $this->log($request, $consumption, 'material_consumption.created', ['item_count' => count($items)]);

            return $consumption;
        });

        return response()->json(['data' => new MaterialConsumptionResource($this->load($consumption)), 'message' => 'Material consumption created successfully.'], 201);
    }

    public function show(Request $request, TenantContext $tenant, int $id, int $consumptionId): JsonResponse
    {
        $consumption = $this->consumption($request, $tenant, $id, $consumptionId);
        if (! $consumption) {
            return $this->missing();
        }
        if (! $request->user()->can('material_consumptions.view')) {
            return $this->denied();
        }

        return response()->json(['data' => new MaterialConsumptionResource($this->load($consumption, true))]);
    }

    public function update(UpdateMaterialConsumptionRequest $request, TenantContext $tenant, int $id, int $consumptionId): JsonResponse
    {
        $consumption = $this->consumption($request, $tenant, $id, $consumptionId, false);
        if (! $consumption) {
            return $this->missing();
        }
        if ($consumption->status !== 'draft') {
            return $this->conflict('Only draft consumptions can be updated.');
        }
        if (! $request->user()->can('material_consumptions.update') || ! $request->user()->can('update', $consumption)) {
            return $this->denied();
        }
        $validated = $request->validated();
        $hasItems = array_key_exists('items', $validated);
        $items = Arr::pull($validated, 'items', []);
        $before = $consumption->only(array_keys($validated));
        DB::transaction(function () use ($consumption, $validated, $hasItems, $items) {
            $consumption->update($validated);
            if ($hasItems) {
                $this->replaceItems($consumption, $items);
            }
        });
        $this->log($request, $consumption, 'material_consumption.updated', ['before' => $before, 'after' => $consumption->only(array_keys($validated)), 'items_replaced' => $hasItems]);

        return response()->json(['data' => new MaterialConsumptionResource($this->load($consumption)), 'message' => 'Material consumption updated successfully.']);
    }

    public function post(PostMaterialConsumptionRequest $request, TenantContext $tenant, int $id, int $consumptionId): JsonResponse
    {
        $consumption = $this->consumption($request, $tenant, $id, $consumptionId, false);
        if (! $consumption) {
            return $this->missing();
        }
        if (! $request->user()->can('material_consumptions.post') || ! $request->user()->can('post', $consumption)) {
            return $this->denied();
        }
        try {
            $transactionIds = DB::transaction(function () use ($request, $tenant, $consumption) {
                $locked = MaterialConsumption::query()->whereKey($consumption->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'draft') {
                    throw new DomainException('duplicate');
                }
                $items = $locked->items()->with('projectInventory.material.measurementUnit')->orderBy('project_inventory_id')->get();
                if ($items->isEmpty()) {
                    throw new DomainException('empty');
                }
                $inventoryIds = $items->pluck('project_inventory_id')->unique()->sort()->values();
                $inventories = ProjectInventory::query()->whereIn('id', $inventoryIds)->where('company_id', $tenant->companyId())->where('project_id', $locked->project_id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                if ($inventories->count() !== $inventoryIds->count()) {
                    throw new DomainException('invalid');
                }
                $required = [];
                foreach ($items as $item) {
                    $inventory = $inventories->get($item->project_inventory_id);
                    if (! $inventory || (int) $inventory->material_id !== (int) $item->material_id) {
                        throw new DomainException('invalid');
                    }
                    $key = (int) $item->project_inventory_id;
                    $required[$key] = DecimalQuantity::add($required[$key] ?? '0.0000', (string) $item->quantity);
                }
                $insufficient = [];
                foreach ($required as $inventoryId => $quantity) {
                    $stock = (string) InventoryTransaction::query()->where('project_inventory_id', $inventoryId)->selectRaw("coalesce(sum(case when direction = 'in' then quantity else -quantity end), 0) as balance")->value('balance');
                    if (DecimalQuantity::compare($stock, $quantity) < 0) {
                        $insufficient[(string) $inventoryId] = ['available' => $stock, 'required' => $quantity];
                    }
                }
                if ($insufficient) {
                    throw new DomainException('insufficient:'.json_encode($insufficient));
                }
                $ids = [];
                foreach ($items as $item) {
                    $tx = InventoryTransaction::forceCreate(['company_id' => $tenant->companyId(), 'project_id' => $locked->project_id, 'project_inventory_id' => $item->project_inventory_id, 'material_id' => $item->material_id, 'consumption_item_id' => $item->id, 'transaction_type' => 'material_consumed', 'quantity' => $item->quantity, 'direction' => 'out', 'reference_type' => MaterialConsumptionItem::class, 'reference_id' => $item->id, 'transaction_date' => $locked->consumption_date, 'notes' => $request->validated('notes') ?? $item->notes, 'created_by' => $request->user()->id]);
                    $ids[] = $tx->id;
                }
                $locked->forceFill(['status' => 'posted', 'recorded_by' => $request->user()->id, 'posted_at' => now()])->save();
                $this->log($request, $locked, 'material_consumption.posted', ['item_count' => $items->count(), 'transaction_ids' => $ids]);

                return $ids;
            });
        } catch (DomainException $exception) {
            if ($exception->getMessage() === 'duplicate') {
                return $this->conflict('This consumption has already been processed.');
            }
            if (str_starts_with($exception->getMessage(), 'insufficient:')) {
                return response()->json(['message' => 'Insufficient stock. No material was consumed.', 'errors' => ['stock' => json_decode(substr($exception->getMessage(), 13), true)]], 422);
            }

            return response()->json(['message' => 'The consumption cannot be posted in its current state.'], 422);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '19'], true)) {
                return $this->conflict('This consumption has already been processed.');
            }
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'The consumption could not be posted. No stock was deducted.'], 500);
        }
        $consumption->refresh();

        return response()->json(['data' => new MaterialConsumptionResource($this->load($consumption, true)), 'transaction_ids' => $transactionIds, 'message' => 'Material consumption posted successfully.']);
    }

    public function cancel(Request $request, TenantContext $tenant, int $id, int $consumptionId): JsonResponse
    {
        $consumption = $this->consumption($request, $tenant, $id, $consumptionId, false);
        if (! $consumption) {
            return $this->missing();
        }
        if ($consumption->status !== 'draft') {
            return $this->conflict('Only draft consumptions can be cancelled.');
        }
        if (! $request->user()->can('material_consumptions.cancel') || ! $request->user()->can('cancel', $consumption)) {
            return $this->denied();
        }
        $consumption->forceFill(['status' => 'cancelled'])->save();
        $this->log($request, $consumption, 'material_consumption.cancelled', ['item_count' => $consumption->items()->count()]);

        return response()->json(['data' => new MaterialConsumptionResource($this->load($consumption)), 'message' => 'Material consumption cancelled successfully.']);
    }

    public function transactions(ListInventoryTransactionsRequest $request, TenantContext $tenant, int $id, int $consumptionId): JsonResponse
    {
        $consumption = $this->consumption($request, $tenant, $id, $consumptionId);
        if (! $consumption) {
            return $this->missing();
        }
        if (! $request->user()->can('material_consumptions.view')) {
            return $this->denied();
        }
        $validated = $request->validated();
        $query = InventoryTransaction::query()->where('company_id', $tenant->companyId())->where('project_id', $id)->whereIn('consumption_item_id', $consumption->items()->select('id'))->with(['creator:id,name', 'projectInventory.material.measurementUnit']);
        if ($value = $validated['date_from'] ?? null) {
            $query->whereDate('transaction_date', '>=', $value);
        }
        if ($value = $validated['date_to'] ?? null) {
            $query->whereDate('transaction_date', '<=', $value);
        }
        $page = $query->orderBy($validated['sort'] ?? 'transaction_date', $validated['direction'] ?? 'desc')->paginate($validated['per_page'] ?? 15);

        return response()->json(['data' => InventoryTransactionResource::collection($page->items()), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function summary(ListMaterialConsumptionsRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->project($request, $tenant, $id);
        if (! $project) {
            return $this->missing();
        }
        if (! $request->user()->can('material_consumptions.view')) {
            return $this->denied();
        }
        $validated = $request->validated();
        $base = MaterialConsumption::query()->forCompany($tenant->companyId())->where('project_id', $id);
        if ($value = $validated['date_from'] ?? null) {
            $base->whereDate('consumption_date', '>=', $value);
        }
        if ($value = $validated['date_to'] ?? null) {
            $base->whereDate('consumption_date', '<=', $value);
        }
        $counts = (clone $base)->selectRaw('count(*) as total_documents')->selectRaw("sum(case when status = 'draft' then 1 else 0 end) as draft_documents")->selectRaw("sum(case when status = 'posted' then 1 else 0 end) as posted_documents")->selectRaw("sum(case when status = 'cancelled' then 1 else 0 end) as cancelled_documents")->first();
        $usage = DB::table('material_consumption_items as i')->join('material_consumptions as c', 'c.id', '=', 'i.material_consumption_id')->join('materials as m', 'm.id', '=', 'i.material_id')->join('measurement_units as u', 'u.id', '=', 'm.measurement_unit_id')->where('c.company_id', $tenant->companyId())->where('c.project_id', $id)->where('c.status', 'posted')->when($validated['date_from'] ?? null, fn ($q, $v) => $q->whereDate('c.consumption_date', '>=', $v))->when($validated['date_to'] ?? null, fn ($q, $v) => $q->whereDate('c.consumption_date', '<=', $v))->groupBy('m.id', 'm.name', 'm.code', 'u.id', 'u.name', 'u.symbol', 'u.decimal_precision')->selectRaw('m.id as material_id, m.name as material_name, m.code as material_code, u.id as unit_id, u.name as unit_name, u.symbol as unit_symbol, u.decimal_precision, sum(i.quantity) as quantity')->orderBy('m.name')->get()->map(fn ($row) => ['material' => ['id' => $row->material_id, 'name' => $row->material_name, 'code' => $row->material_code], 'measurement_unit' => ['id' => $row->unit_id, 'name' => $row->unit_name, 'symbol' => $row->unit_symbol], 'quantity' => $this->decimalString($row->quantity, (int) $row->decimal_precision)])->values();

        return response()->json(['data' => ['total_documents' => (int) $counts->total_documents, 'draft_documents' => (int) $counts->draft_documents, 'posted_documents' => (int) $counts->posted_documents, 'cancelled_documents' => (int) $counts->cancelled_documents, 'consumed_by_material' => $usage]]);
    }

    private function replaceItems(MaterialConsumption $consumption, array $items): void
    {
        $consumption->items()->delete();
        foreach ($items as $data) {
            $inventory = ProjectInventory::query()->where('company_id', $consumption->company_id)->where('project_id', $consumption->project_id)->findOrFail($data['project_inventory_id']);
            $item = new MaterialConsumptionItem($data);
            $item->forceFill(['company_id' => $consumption->company_id, 'material_consumption_id' => $consumption->id, 'material_id' => $inventory->material_id])->save();
        }
    }

    private function project(Request $request, TenantContext $tenant, int $id): ?Project
    {
        $project = Project::query()->forCompany($tenant->companyId())->find($id);

        return $project && $request->user()->can('view', $project) ? $project : null;
    }

    private function consumption(Request $request, TenantContext $tenant, int $projectId, int $id, bool $view = true): ?MaterialConsumption
    {
        if (! $this->project($request, $tenant, $projectId)) {
            return null;
        }
        $consumption = MaterialConsumption::query()->forCompany($tenant->companyId())->where('project_id', $projectId)->find($id);

        return $consumption && (! $view || $request->user()->can('view', $consumption)) ? $consumption : null;
    }

    private function load(MaterialConsumption $consumption, bool $stock = false): MaterialConsumption
    {
        $consumption->load(['project:id,name,code', 'recorder:id,name', 'creator:id,name', 'items.material.category', 'items.material.measurementUnit', 'items.inventoryTransactions']);
        if ($stock) {
            $balances = InventoryTransaction::query()->whereIn('project_inventory_id', $consumption->items->pluck('project_inventory_id'))
                ->groupBy('project_inventory_id')->select('project_inventory_id')
                ->selectRaw("coalesce(sum(case when direction = 'in' then quantity else -quantity end), 0) as balance")
                ->pluck('balance', 'project_inventory_id');
            foreach ($consumption->items as $item) {
                $item->current_stock = $balances->get($item->project_inventory_id, '0.0000');
            }
        }

        return $consumption;
    }

    private function log(Request $request, MaterialConsumption $consumption, string $event, array $changes): void
    {
        activity('material_consumptions')->causedBy($request->user())->performedOn($consumption)->event($event)->withProperties(['actor_id' => $request->user()->id, 'company_id' => $consumption->company_id, 'project_id' => $consumption->project_id, 'consumption_id' => $consumption->id, 'consumption_number' => $consumption->consumption_number, 'timestamp' => now()->toIso8601String(), 'changes' => $changes])->log($event);
    }

    private function decimalString(mixed $value, int $precision): string
    {
        $parts = explode('.', (string) $value, 2);

        return $precision === 0 ? $parts[0] : $parts[0].'.'.str_pad(substr($parts[1] ?? '', 0, $precision), $precision, '0');
    }

    private function denied(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Material consumption not found.'], 404);
    }

    private function conflict(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 409);
    }
}
