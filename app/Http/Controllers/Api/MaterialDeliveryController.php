<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListMaterialDeliveriesRequest;
use App\Http\Requests\Api\V1\ReceiveMaterialDeliveryRequest;
use App\Http\Requests\Api\V1\StoreMaterialDeliveryRequest;
use App\Http\Requests\Api\V1\UpdateMaterialDeliveryRequest;
use App\Http\Resources\Api\V1\MaterialDeliveryResource;
use App\Models\InventoryTransaction;
use App\Models\MaterialDelivery;
use App\Models\MaterialDeliveryItem;
use App\Models\Project;
use App\Models\ProjectInventory;
use App\Support\Tenancy\TenantContext;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class MaterialDeliveryController extends Controller
{
    public function index(ListMaterialDeliveriesRequest $r, TenantContext $t, int $id): JsonResponse
    {
        $p = $this->project($r, $t, $id);
        if (! $p) {
            return $this->missing();
        }if (! $r->user()->can('material_deliveries.view')) {
            return $this->denied();
        }$v = $r->validated();
        $q = MaterialDelivery::query()->forCompany($t->companyId())->where('project_id', $p->id)->with(['supplier', 'project:id,name,code', 'receiver:id,name', 'creator:id,name'])->withCount('items');
        if ($s = $v['search'] ?? null) {
            $q->where('delivery_number', 'like', "%{$s}%");
        }if ($x = $v['supplier_id'] ?? null) {
            $q->where('supplier_id', $x);
        }if ($x = $v['status'] ?? null) {
            $q->where('status', $x);
        }if ($x = $v['date_from'] ?? null) {
            $q->whereDate('delivery_date', '>=', $x);
        }if ($x = $v['date_to'] ?? null) {
            $q->whereDate('delivery_date', '<=', $x);
        }$page = $q->orderBy($v['sort'] ?? 'delivery_date', $v['direction'] ?? 'desc')->paginate($v['per_page'] ?? 15);

        return response()->json(['data' => MaterialDeliveryResource::collection($page->items()), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function store(StoreMaterialDeliveryRequest $r, TenantContext $t, int $id): JsonResponse
    {
        $p = $this->project($r, $t, $id);
        if (! $p) {
            return $this->missing();
        }if (! $r->user()->can('material_deliveries.create') || ! $r->user()->can('create', [MaterialDelivery::class, $p])) {
            return $this->denied();
        }$v = $r->validated();
        $items = Arr::pull($v, 'items');
        $d = DB::transaction(function () use ($r, $t, $p, $v, $items) {
            $d = new MaterialDelivery($v);
            $d->forceFill(['company_id' => $t->companyId(), 'project_id' => $p->id, 'delivery_number' => 'DLV-'.now()->format('Ymd').'-'.Str::upper((string) Str::ulid()), 'status' => 'draft', 'created_by' => $r->user()->id]);
            $d->save();
            $this->replaceItems($d, $items);
            $this->log($r, $d, 'material_delivery.created', ['item_count' => count($items)]);

            return $d;
        });

        return response()->json(['data' => new MaterialDeliveryResource($this->load($d)), 'message' => 'Material delivery created successfully.'], 201);
    }

    public function show(Request $r, TenantContext $t, int $id, int $deliveryId): JsonResponse
    {
        $d = $this->delivery($r, $t, $id, $deliveryId);
        if (! $d) {
            return $this->missing();
        }if (! $r->user()->can('material_deliveries.view')) {
            return $this->denied();
        }

        return response()->json(['data' => new MaterialDeliveryResource($this->load($d))]);
    }

    public function update(UpdateMaterialDeliveryRequest $r, TenantContext $t, int $id, int $deliveryId): JsonResponse
    {
        $d = $this->delivery($r, $t, $id, $deliveryId, false);
        if (! $d) {
            return $this->missing();
        }if (! $r->user()->can('material_deliveries.update') || ! $r->user()->can('update', $d)) {
            return $d->status === 'draft' ? $this->denied() : $this->conflict('Only draft deliveries can be updated.');
        }$v = $r->validated();
        $hasItems = array_key_exists('items', $v);
        $items = Arr::pull($v, 'items', []);
        $before = $d->only(array_keys($v));
        DB::transaction(function () use ($d, $v, $hasItems, $items) {
            $d->update($v);
            if ($hasItems) {
                $this->replaceItems($d, $items);
            }
        });
        $this->log($r, $d, 'material_delivery.updated', ['before' => $before, 'after' => $d->only(array_keys($v)), 'items_replaced' => $hasItems]);

        return response()->json(['data' => new MaterialDeliveryResource($this->load($d)), 'message' => 'Material delivery updated successfully.']);
    }

    public function receive(ReceiveMaterialDeliveryRequest $r, TenantContext $t, int $id, int $deliveryId): JsonResponse
    {
        $d = $this->delivery($r, $t, $id, $deliveryId, false);
        if (! $d) {
            return $this->missing();
        }if (! $r->user()->can('material_deliveries.receive') || ! $r->user()->can('receive', $d)) {
            return $this->denied();
        }try {
            $transactionIds = DB::transaction(function () use ($r, $t, $d) {
                $locked = MaterialDelivery::query()->whereKey($d->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'draft') {
                    throw new DomainException('duplicate');
                }$items = $locked->items()->with('projectInventory.material.measurementUnit')->orderBy('project_inventory_id')->get();
                if ($items->isEmpty()) {
                    throw new DomainException('empty');
                }$inventoryIds = $items->pluck('project_inventory_id')->unique()->sort()->values();
                $inventories = ProjectInventory::query()->whereIn('id', $inventoryIds)->where('company_id', $t->companyId())->where('project_id', $locked->project_id)->lockForUpdate()->get()->keyBy('id');
                if ($inventories->count() !== $inventoryIds->count()) {
                    throw new DomainException('invalid');
                }$ids = [];
                foreach ($items as $item) {
                    $inventory = $inventories->get($item->project_inventory_id);
                    if (! $inventory || (int) $inventory->material_id !== (int) $item->material_id) {
                        throw new DomainException('invalid');
                    }$tx = InventoryTransaction::forceCreate(['company_id' => $t->companyId(), 'project_id' => $locked->project_id, 'project_inventory_id' => $inventory->id, 'material_id' => $item->material_id, 'delivery_item_id' => $item->id, 'transaction_type' => 'material_received', 'quantity' => $item->quantity, 'direction' => 'in', 'reference_type' => MaterialDeliveryItem::class, 'reference_id' => $item->id, 'transaction_date' => $locked->delivery_date, 'notes' => $r->validated('notes'), 'created_by' => $r->user()->id]);
                    $ids[] = $tx->id;
                }$locked->forceFill(['status' => 'received', 'received_at' => $r->validated('received_at'), 'received_by' => $r->user()->id, 'submitted_at' => now(), 'posted_at' => now()])->save();
                $this->log($r, $locked, 'material_delivery.received', ['item_count' => $items->count(), 'transaction_ids' => $ids]);

                return $ids;
            });
        } catch (DomainException $e) {
            return $e->getMessage() === 'duplicate' ? $this->conflict('This delivery has already been processed.') : response()->json(['message' => 'The delivery cannot be received in its current state.'], 422);
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '19'], true)) {
                return $this->conflict('This delivery has already been processed.');
            }throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'The delivery could not be received. No stock was posted.'], 500);
        }$d->refresh();

        return response()->json(['data' => new MaterialDeliveryResource($this->load($d)), 'transaction_ids' => $transactionIds, 'message' => 'Material delivery received successfully.']);
    }

    public function cancel(Request $r, TenantContext $t, int $id, int $deliveryId): JsonResponse
    {
        $d = $this->delivery($r, $t, $id, $deliveryId, false);
        if (! $d) {
            return $this->missing();
        }if (! $r->user()->can('material_deliveries.cancel') || ! $r->user()->can('cancel', $d)) {
            return $this->denied();
        }if ($d->status !== 'draft') {
            return $this->conflict('Only draft deliveries can be cancelled.');
        }$d->status = 'cancelled';
        $d->save();
        $this->log($r, $d, 'material_delivery.cancelled', ['item_count' => $d->items()->count()]);

        return response()->json(['data' => new MaterialDeliveryResource($this->load($d)), 'message' => 'Material delivery cancelled successfully.']);
    }

    private function replaceItems(MaterialDelivery $d, array $items): void
    {
        $d->items()->delete();
        foreach ($items as $data) {
            $inventory = ProjectInventory::findOrFail($data['project_inventory_id']);
            $item = new MaterialDeliveryItem($data);
            $item->forceFill(['company_id' => $d->company_id, 'material_delivery_id' => $d->id, 'material_id' => $inventory->material_id]);
            $item->save();
        }
    }

    private function project(Request $r, TenantContext $t, int $id): ?Project
    {
        $p = Project::query()->forCompany($t->companyId())->find($id);

        return $p && $r->user()->can('view', $p) ? $p : null;
    }

    private function delivery(Request $r, TenantContext $t, int $projectId, int $id, bool $view = true): ?MaterialDelivery
    {
        if (! $this->project($r, $t, $projectId)) {
            return null;
        }$d = MaterialDelivery::query()->forCompany($t->companyId())->where('project_id', $projectId)->find($id);

        return $d && (! $view || $r->user()->can('view', $d)) ? $d : null;
    }

    private function load(MaterialDelivery $d): MaterialDelivery
    {
        return $d->load(['supplier', 'project:id,name,code', 'receiver:id,name', 'creator:id,name', 'items.material.category', 'items.material.measurementUnit', 'items.inventoryTransactions']);
    }

    private function log(Request $r, MaterialDelivery $d, string $e, array $c): void
    {
        activity('material_deliveries')->causedBy($r->user())->performedOn($d)->event($e)->withProperties(['company_id' => $d->company_id, 'project_id' => $d->project_id, 'supplier_id' => $d->supplier_id, 'delivery_id' => $d->id, 'delivery_number' => $d->delivery_number, 'changes' => $c])->log($e);
    }

    private function denied(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Material delivery not found.'], 404);
    }

    private function conflict(string $m): JsonResponse
    {
        return response()->json(['message' => $m], 409);
    }
}
