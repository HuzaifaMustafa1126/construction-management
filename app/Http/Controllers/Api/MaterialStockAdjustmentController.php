<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListMaterialStockAdjustmentsRequest;
use App\Http\Requests\Api\V1\PostMaterialStockAdjustmentRequest;
use App\Http\Requests\Api\V1\StoreMaterialStockAdjustmentRequest;
use App\Http\Requests\Api\V1\UpdateMaterialStockAdjustmentRequest;
use App\Http\Resources\Api\V1\MaterialStockAdjustmentResource;
use App\Models\InventoryTransaction;
use App\Models\MaterialStockAdjustment;
use App\Models\MaterialStockAdjustmentItem;
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

class MaterialStockAdjustmentController extends Controller
{
    public function index(ListMaterialStockAdjustmentsRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->project($request, $tenant, $id);
        if (! $project) {
            return $this->missing();
        }
        if (! $request->user()->can('stock_adjustments.view')) {
            return $this->denied();
        }
        $query = $this->filteredQuery($request, $tenant, $id)->with(['project:id,name,code', 'creator:id,name', 'poster:id,name', 'canceller:id,name'])->withCount('items');
        $validated = $request->validated();
        $page = $query->orderBy($validated['sort'] ?? 'adjustment_date', $validated['direction'] ?? 'desc')->paginate($validated['per_page'] ?? 15);

        return response()->json(['data' => MaterialStockAdjustmentResource::collection($page->items()), 'meta' => $this->meta($page)]);
    }

    public function store(StoreMaterialStockAdjustmentRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->project($request, $tenant, $id);
        if (! $project) {
            return $this->missing();
        }
        if (! $request->user()->can('stock_adjustments.create') || ! $request->user()->can('create', [MaterialStockAdjustment::class, $project])) {
            return $this->denied();
        }
        $validated = $request->validated();
        $items = Arr::pull($validated, 'items');
        $adjustment = DB::transaction(function () use ($request, $tenant, $project, $validated, $items) {
            $adjustment = new MaterialStockAdjustment($validated);
            $adjustment->forceFill(['company_id' => $tenant->companyId(), 'project_id' => $project->id, 'adjustment_number' => 'ADJ-'.now()->format('Ymd').'-'.Str::upper((string) Str::ulid()), 'status' => 'draft', 'created_by' => $request->user()->id])->save();
            $this->replaceItems($adjustment, $items);
            $this->log($request, $adjustment, 'material_stock_adjustment.created', ['item_count' => count($items)]);

            return $adjustment;
        });

        return response()->json(['data' => new MaterialStockAdjustmentResource($this->load($adjustment)), 'message' => 'Stock adjustment created successfully.'], 201);
    }

    public function show(Request $request, TenantContext $tenant, int $id, int $adjustmentId): JsonResponse
    {
        $adjustment = $this->adjustment($request, $tenant, $id, $adjustmentId);
        if (! $adjustment) {
            return $this->missing();
        }
        if (! $request->user()->can('stock_adjustments.view')) {
            return $this->denied();
        }

        return response()->json(['data' => new MaterialStockAdjustmentResource($this->load($adjustment, true))]);
    }

    public function update(UpdateMaterialStockAdjustmentRequest $request, TenantContext $tenant, int $id, int $adjustmentId): JsonResponse
    {
        $adjustment = $this->adjustment($request, $tenant, $id, $adjustmentId, false);
        if (! $adjustment) {
            return $this->missing();
        }
        if ($adjustment->status !== 'draft') {
            return $this->conflict('Only draft stock adjustments can be updated.');
        }
        if (! $request->user()->can('stock_adjustments.update') || ! $request->user()->can('update', $adjustment)) {
            return $this->denied();
        }
        $validated = $request->validated();
        $hasItems = array_key_exists('items', $validated);
        $items = Arr::pull($validated, 'items', []);
        DB::transaction(function () use ($adjustment, $validated, $hasItems, $items) {
            $adjustment->update($validated);
            if ($hasItems) {
                $this->replaceItems($adjustment, $items);
            }
        });
        $this->log($request, $adjustment, 'material_stock_adjustment.updated', ['items_replaced' => $hasItems]);

        return response()->json(['data' => new MaterialStockAdjustmentResource($this->load($adjustment)), 'message' => 'Stock adjustment updated successfully.']);
    }

    public function post(PostMaterialStockAdjustmentRequest $request, TenantContext $tenant, int $id, int $adjustmentId): JsonResponse
    {
        $adjustment = $this->adjustment($request, $tenant, $id, $adjustmentId, false);
        if (! $adjustment) {
            return $this->missing();
        }
        if (! $request->user()->can('stock_adjustments.post') || ! $request->user()->can('post', $adjustment)) {
            return $this->denied();
        }
        try {
            $transactionIds = DB::transaction(function () use ($request, $tenant, $adjustment) {
                $locked = MaterialStockAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'draft') {
                    throw new DomainException('duplicate');
                }
                $items = $locked->items()->orderBy('project_inventory_id')->get();
                if ($items->isEmpty()) {
                    throw new DomainException('invalid');
                }
                $inventoryIds = $items->pluck('project_inventory_id')->unique()->sort()->values();
                $inventories = ProjectInventory::query()->whereIn('id', $inventoryIds)->where('company_id', $tenant->companyId())->where('project_id', $locked->project_id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                if ($inventories->count() !== $inventoryIds->count()) {
                    throw new DomainException('invalid');
                }
                $outgoing = [];
                foreach ($items as $item) {
                    $inventory = $inventories->get($item->project_inventory_id);
                    if (! $inventory || (int) $inventory->material_id !== (int) $item->material_id) {
                        throw new DomainException('invalid');
                    }
                    $isCorrection = in_array($item->adjustment_type, MaterialStockAdjustmentItem::CORRECTION_TYPES, true);
                    $referenceValid = $item->reference_inventory_transaction_id && InventoryTransaction::query()->whereKey($item->reference_inventory_transaction_id)->where('company_id', $locked->company_id)->where('project_id', $locked->project_id)->where('project_inventory_id', $item->project_inventory_id)->exists();
                    if (($isCorrection && (! $referenceValid || blank($item->correction_explanation))) || (! $isCorrection && $item->reference_inventory_transaction_id)) {
                        throw new DomainException('invalid');
                    }
                    if (MaterialStockAdjustmentItem::direction($item->adjustment_type) === 'out') {
                        $outgoing[$item->project_inventory_id] = DecimalQuantity::add($outgoing[$item->project_inventory_id] ?? '0.0000', (string) $item->quantity);
                    }
                }
                $insufficient = [];
                foreach ($outgoing as $inventoryId => $required) {
                    $available = (string) InventoryTransaction::query()->where('project_inventory_id', $inventoryId)->selectRaw("coalesce(sum(case when direction = 'in' then quantity else -quantity end), 0) as balance")->value('balance');
                    if (DecimalQuantity::compare($available, $required) < 0) {
                        $insufficient[(string) $inventoryId] = ['available' => $available, 'required' => $required];
                    }
                }
                if ($insufficient) {
                    throw new DomainException('insufficient:'.json_encode($insufficient));
                }
                $ids = [];
                foreach ($items as $item) {
                    $transaction = InventoryTransaction::forceCreate(['company_id' => $locked->company_id, 'project_id' => $locked->project_id, 'project_inventory_id' => $item->project_inventory_id, 'material_id' => $item->material_id, 'stock_adjustment_item_id' => $item->id, 'transaction_type' => $item->adjustment_type, 'quantity' => $item->quantity, 'direction' => MaterialStockAdjustmentItem::direction($item->adjustment_type), 'reference_type' => MaterialStockAdjustmentItem::class, 'reference_id' => $item->id, 'transaction_date' => $locked->adjustment_date, 'notes' => $request->validated('notes') ?? $item->notes, 'created_by' => $request->user()->id]);
                    $ids[] = $transaction->id;
                }
                $locked->forceFill(['status' => 'posted', 'posted_by' => $request->user()->id, 'posted_at' => now()])->save();
                $this->log($request, $locked, 'material_stock_adjustment.posted', ['items' => $items->map(fn ($item) => ['material_id' => $item->material_id, 'quantity' => $item->quantity, 'direction' => MaterialStockAdjustmentItem::direction($item->adjustment_type), 'reason_code' => $item->reason_code])->all(), 'transaction_ids' => $ids]);

                return $ids;
            });
        } catch (DomainException $exception) {
            if ($exception->getMessage() === 'duplicate') {
                return $this->conflict('This stock adjustment has already been processed.');
            }
            if (str_starts_with($exception->getMessage(), 'insufficient:')) {
                return response()->json(['message' => 'Insufficient stock. No adjustment was posted.', 'errors' => ['stock' => json_decode(substr($exception->getMessage(), 13), true)]], 422);
            }

            return response()->json(['message' => 'The stock adjustment cannot be posted in its current state.'], 422);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '19'], true)) {
                return $this->conflict('This stock adjustment has already been processed.');
            }
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'The stock adjustment could not be posted. No stock was changed.'], 500);
        }
        $adjustment->refresh();

        return response()->json(['data' => new MaterialStockAdjustmentResource($this->load($adjustment, true)), 'transaction_ids' => $transactionIds, 'message' => 'Stock adjustment posted successfully.']);
    }

    public function cancel(Request $request, TenantContext $tenant, int $id, int $adjustmentId): JsonResponse
    {
        $adjustment = $this->adjustment($request, $tenant, $id, $adjustmentId, false);
        if (! $adjustment) {
            return $this->missing();
        }
        if ($adjustment->status !== 'draft') {
            return $this->conflict('Only draft stock adjustments can be cancelled.');
        }
        if (! $request->user()->can('stock_adjustments.cancel') || ! $request->user()->can('cancel', $adjustment)) {
            return $this->denied();
        }
        $adjustment->forceFill(['status' => 'cancelled', 'cancelled_by' => $request->user()->id, 'cancelled_at' => now()])->save();
        $this->log($request, $adjustment, 'material_stock_adjustment.cancelled', ['item_count' => $adjustment->items()->count()]);

        return response()->json(['data' => new MaterialStockAdjustmentResource($this->load($adjustment)), 'message' => 'Stock adjustment cancelled successfully.']);
    }

    public function history(ListMaterialStockAdjustmentsRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->project($request, $tenant, $id);
        if (! $project) {
            return $this->missing();
        }
        if (! $request->user()->can('stock_adjustments.view')) {
            return $this->denied();
        }
        $validated = $request->validated();
        $query = $this->filteredQuery($request, $tenant, $id)->where('status', 'posted')->with(['project:id,name,code', 'creator:id,name', 'poster:id,name', 'items.material.measurementUnit', 'items.referenceTransaction.creator:id,name', 'items.referenceTransaction.projectInventory.material.measurementUnit', 'items.inventoryTransactions']);
        $page = $query->orderBy($validated['sort'] ?? 'adjustment_date', $validated['direction'] ?? 'desc')->paginate($validated['per_page'] ?? 15);

        return response()->json(['data' => MaterialStockAdjustmentResource::collection($page->items()), 'meta' => $this->meta($page)]);
    }

    public function summary(ListMaterialStockAdjustmentsRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->project($request, $tenant, $id);
        if (! $project) {
            return $this->missing();
        }
        if (! $request->user()->can('stock_adjustments.view')) {
            return $this->denied();
        }
        $validated = $request->validated();
        $base = $this->filteredQuery($request, $tenant, $id);
        $counts = (clone $base)->selectRaw('count(*) total_documents')->selectRaw("sum(case when status='draft' then 1 else 0 end) draft_documents")->selectRaw("sum(case when status='posted' then 1 else 0 end) posted_documents")->selectRaw("sum(case when status='cancelled' then 1 else 0 end) cancelled_documents")->first();
        $breakdown = DB::table('material_stock_adjustment_items as i')->join('material_stock_adjustments as a', 'a.id', '=', 'i.material_stock_adjustment_id')->join('materials as m', 'm.id', '=', 'i.material_id')->join('measurement_units as u', 'u.id', '=', 'm.measurement_unit_id')->where('a.company_id', $tenant->companyId())->where('a.project_id', $id)->where('a.status', 'posted')->when($validated['date_from'] ?? null, fn ($q, $v) => $q->whereDate('a.adjustment_date', '>=', $v))->when($validated['date_to'] ?? null, fn ($q, $v) => $q->whereDate('a.adjustment_date', '<=', $v))->groupBy('i.adjustment_type', 'm.id', 'm.name', 'm.code', 'u.id', 'u.name', 'u.symbol', 'u.decimal_precision')->selectRaw('i.adjustment_type, m.id material_id, m.name material_name, m.code material_code, u.id unit_id, u.name unit_name, u.symbol unit_symbol, u.decimal_precision, sum(i.quantity) quantity')->orderBy('m.name')->get()->map(fn ($row) => ['adjustment_type' => $row->adjustment_type, 'material' => ['id' => $row->material_id, 'name' => $row->material_name, 'code' => $row->material_code], 'measurement_unit' => ['id' => $row->unit_id, 'name' => $row->unit_name, 'symbol' => $row->unit_symbol], 'quantity' => $this->decimalString($row->quantity, (int) $row->decimal_precision)])->values();

        return response()->json(['data' => ['total_documents' => (int) $counts->total_documents, 'draft_documents' => (int) $counts->draft_documents, 'posted_documents' => (int) $counts->posted_documents, 'cancelled_documents' => (int) $counts->cancelled_documents, 'breakdown' => $breakdown]]);
    }

    private function filteredQuery(ListMaterialStockAdjustmentsRequest $request, TenantContext $tenant, int $projectId)
    {
        $validated = $request->validated();
        $query = MaterialStockAdjustment::query()->forCompany($tenant->companyId())->where('project_id', $projectId);
        if ($value = $validated['search'] ?? null) {
            $query->where('adjustment_number', 'like', "%{$value}%");
        }
        if ($value = $validated['status'] ?? null) {
            $query->where('status', $value);
        }
        if ($value = $validated['date_from'] ?? null) {
            $query->whereDate('adjustment_date', '>=', $value);
        }
        if ($value = $validated['date_to'] ?? null) {
            $query->whereDate('adjustment_date', '<=', $value);
        }
        if ($value = $validated['created_by'] ?? null) {
            $query->where('created_by', $value);
        }

        return $query;
    }

    private function replaceItems(MaterialStockAdjustment $adjustment, array $items): void
    {
        $adjustment->items()->delete();
        foreach ($items as $data) {
            $inventory = ProjectInventory::query()->where('company_id', $adjustment->company_id)->where('project_id', $adjustment->project_id)->findOrFail($data['project_inventory_id']);
            $item = new MaterialStockAdjustmentItem($data);
            $item->forceFill(['company_id' => $adjustment->company_id, 'material_stock_adjustment_id' => $adjustment->id, 'material_id' => $inventory->material_id])->save();
        }
    }

    private function project(Request $request, TenantContext $tenant, int $id): ?Project
    {
        $project = Project::query()->forCompany($tenant->companyId())->find($id);

        return $project && $request->user()->can('view', $project) ? $project : null;
    }

    private function adjustment(Request $request, TenantContext $tenant, int $projectId, int $id, bool $view = true): ?MaterialStockAdjustment
    {
        if (! $this->project($request, $tenant, $projectId)) {
            return null;
        }
        $adjustment = MaterialStockAdjustment::query()->forCompany($tenant->companyId())->where('project_id', $projectId)->find($id);

        return $adjustment && (! $view || $request->user()->can('view', $adjustment)) ? $adjustment : null;
    }

    private function load(MaterialStockAdjustment $adjustment, bool $stock = false): MaterialStockAdjustment
    {
        $adjustment->load(['project:id,name,code', 'creator:id,name', 'poster:id,name', 'canceller:id,name', 'items.material.measurementUnit', 'items.referenceTransaction.creator:id,name', 'items.referenceTransaction.projectInventory.material.measurementUnit', 'items.inventoryTransactions']);
        if ($stock) {
            $balances = InventoryTransaction::query()->whereIn('project_inventory_id', $adjustment->items->pluck('project_inventory_id'))->groupBy('project_inventory_id')->select('project_inventory_id')->selectRaw("coalesce(sum(case when direction='in' then quantity else -quantity end), 0) balance")->pluck('balance', 'project_inventory_id');
            foreach ($adjustment->items as $item) {
                $item->current_stock = $balances->get($item->project_inventory_id, '0.0000');
            }
        }

        return $adjustment;
    }

    private function log(Request $request, MaterialStockAdjustment $adjustment, string $event, array $changes): void
    {
        activity('material_stock_adjustments')->causedBy($request->user())->performedOn($adjustment)->event($event)->withProperties(['actor_id' => $request->user()->id, 'company_id' => $adjustment->company_id, 'project_id' => $adjustment->project_id, 'adjustment_id' => $adjustment->id, 'adjustment_number' => $adjustment->adjustment_number, 'timestamp' => now()->toIso8601String(), 'changes' => $changes])->log($event);
    }

    private function meta($page): array
    {
        return ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()];
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
        return response()->json(['message' => 'Stock adjustment not found.'], 404);
    }

    private function conflict(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 409);
    }
}
