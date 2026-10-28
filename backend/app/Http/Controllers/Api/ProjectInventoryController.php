<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListInventoryTransactionsRequest;
use App\Http\Requests\Api\V1\ListProjectInventoryRequest;
use App\Http\Requests\Api\V1\StoreOpeningStockRequest;
use App\Http\Requests\Api\V1\StoreProjectInventoryRequest;
use App\Http\Requests\Api\V1\UpdateProjectInventoryRequest;
use App\Http\Resources\Api\V1\InventoryTransactionResource;
use App\Http\Resources\Api\V1\ProjectInventoryResource;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectInventory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectInventoryController extends Controller
{
    public function index(ListProjectInventoryRequest $r, TenantContext $t, int $id): JsonResponse
    {
        $p = $this->project($r, $t, $id);
        if (! $p) {
            return $this->missing();
        }if (! $r->user()->can('inventory.view')) {
            return $this->denied();
        }$v = $r->validated();
        $q = $this->balanced(ProjectInventory::query()->forCompany($t->companyId())->where('project_id', $p->id))->with($this->relations());
        if ($s = $v['search'] ?? null) {
            $q->whereHas('material', fn ($x) => $x->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
        }if ($c = $v['material_category_id'] ?? null) {
            $q->whereHas('material', fn ($x) => $x->where('material_category_id', $c));
        }if (array_key_exists('material_status', $v)) {
            $q->whereHas('material', fn ($x) => $x->where('is_active', $v['material_status']));
        }if (($v['low_stock'] ?? false)) {
            $q->whereRaw('('.$this->balanceSql().') < project_inventories.minimum_stock_level');
        }if ($status = $v['stock_status'] ?? null) {
            $balance = '('.$this->balanceSql().')';
            match ($status) {
                'out_of_stock' => $q->whereRaw("{$balance} = 0"),
                'low_stock' => $q->whereRaw("{$balance} > 0 and {$balance} <= project_inventories.minimum_stock_level"),
                'good' => $q->whereRaw("{$balance} > project_inventories.minimum_stock_level"),
            };
        }$sort = $v['sort'] ?? 'material_name';
        if ($sort === 'material_name') {
            $q->orderBy(Material::select('name')->whereColumn('materials.id', 'project_inventories.material_id'), $v['direction'] ?? 'asc');
        } else {
            $q->orderBy('created_at', $v['direction'] ?? 'desc');
        }$page = $q->paginate($v['per_page'] ?? 15);

        return response()->json(['data' => ProjectInventoryResource::collection($page->items()), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function store(StoreProjectInventoryRequest $r, TenantContext $t, int $id): JsonResponse
    {
        $p = $this->project($r, $t, $id);
        if (! $p) {
            return $this->missing();
        }if (! $r->user()->can('inventory.create') || ! $r->user()->can('create', [ProjectInventory::class, $p])) {
            return $this->denied();
        }$v = $r->validated();
        try {
            $i = new ProjectInventory($v);
            $i->company_id = $t->companyId();
            $i->project_id = $p->id;
            $i->created_by = $r->user()->id;
            $i->save();
        } catch (QueryException $e) {
            if ($this->duplicate($e)) {
                return $this->conflict('This material is already assigned to the project.');
            }throw $e;
        }$this->log($r, $i, 'project_inventory.created', ['minimum_stock_level' => $i->minimum_stock_level]);

        return response()->json(['data' => new ProjectInventoryResource($this->load($this->balanced(ProjectInventory::whereKey($i->id))->firstOrFail())), 'message' => 'Material assigned to project inventory successfully.'], 201);
    }

    public function show(Request $r, TenantContext $t, int $id, int $inventoryId): JsonResponse
    {
        $i = $this->inventory($r, $t, $id, $inventoryId);
        if (! $i) {
            return $this->missing();
        }if (! $r->user()->can('inventory.view')) {
            return $this->denied();
        }$i = $this->balanced(ProjectInventory::whereKey($i->id))->with($this->relations(true))->firstOrFail();

        return response()->json(['data' => new ProjectInventoryResource($i)]);
    }

    public function update(UpdateProjectInventoryRequest $r, TenantContext $t, int $id, int $inventoryId): JsonResponse
    {
        $i = $this->inventory($r, $t, $id, $inventoryId);
        if (! $i) {
            return $this->missing();
        }if (! $r->user()->can('inventory.update') || ! $r->user()->can('update', $i)) {
            return $this->denied();
        }$before = $i->minimum_stock_level;
        $i->update($r->validated());
        $this->log($r, $i, 'project_inventory.minimum_stock_updated', ['before' => $before, 'after' => $i->minimum_stock_level]);
        $i = $this->balanced(ProjectInventory::whereKey($i->id))->with($this->relations())->firstOrFail();

        return response()->json(['data' => new ProjectInventoryResource($i), 'message' => 'Minimum stock level updated successfully.']);
    }

    public function openingStock(StoreOpeningStockRequest $r, TenantContext $t, int $id, int $inventoryId): JsonResponse
    {
        $i = $this->inventory($r, $t, $id, $inventoryId);
        if (! $i) {
            return $this->missing();
        }if (! $r->user()->can('inventory.opening_stock') || ! $r->user()->can('openingStock', $i)) {
            return $this->denied();
        }try {
            $tx = DB::transaction(function () use ($r, $t, $i) {
                $locked = ProjectInventory::query()->whereKey($i->id)->lockForUpdate()->firstOrFail();
                if ($locked->transactions()->where('transaction_type', 'opening_stock')->exists()) {
                    throw new \DomainException('duplicate');
                }$tx = new InventoryTransaction($r->safe()->only(['transaction_date', 'notes']));
                $tx->forceFill(['company_id' => $t->companyId(), 'project_id' => $locked->project_id, 'project_inventory_id' => $locked->id, 'material_id' => $locked->material_id, 'transaction_type' => 'opening_stock', 'quantity' => (string) $r->validated('quantity'), 'direction' => 'in', 'created_by' => $r->user()->id]);
                $tx->save();
                $this->log($r, $locked, 'inventory.opening_stock_recorded', ['transaction_id' => $tx->id, 'quantity' => $tx->quantity, 'transaction_date' => $tx->transaction_date->format('Y-m-d')]);

                return $tx;
            });
        } catch (\DomainException $e) {
            return $this->conflict('Opening stock has already been recorded.');
        } catch (QueryException $e) {
            if ($this->duplicate($e)) {
                return $this->conflict('Opening stock has already been recorded.');
            }throw $e;
        }$tx->load(['creator:id,name', 'projectInventory.material.measurementUnit']);

        return response()->json(['data' => new InventoryTransactionResource($tx), 'message' => 'Opening stock recorded successfully.'], 201);
    }

    public function transactions(ListInventoryTransactionsRequest $r, TenantContext $t, int $id, int $inventoryId): JsonResponse
    {
        $i = $this->inventory($r, $t, $id, $inventoryId);
        if (! $i) {
            return $this->missing();
        }if (! $r->user()->can('inventory.view')) {
            return $this->denied();
        }$v = $r->validated();
        $q = $i->transactions()->with(['creator:id,name', 'projectInventory.material.measurementUnit']);
        if ($from = $v['date_from'] ?? null) {
            $q->whereDate('transaction_date', '>=', $from);
        }if ($to = $v['date_to'] ?? null) {
            $q->whereDate('transaction_date', '<=', $to);
        }if ($type = $v['transaction_type'] ?? null) {
            $q->where('transaction_type', $type);
        }$page = $q->orderBy($v['sort'] ?? 'transaction_date', $v['direction'] ?? 'desc')->paginate($v['per_page'] ?? 15);

        return response()->json(['data' => InventoryTransactionResource::collection($page->items()), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function overview(Request $r, TenantContext $t, int $id): JsonResponse
    {
        $p = $this->project($r, $t, $id);
        if (! $p) {
            return $this->missing();
        }if (! $r->user()->can('inventory.view')) {
            return $this->denied();
        }$rows = $this->balanced(ProjectInventory::query()->forCompany($t->companyId())->where('project_id', $p->id))->get(['project_inventories.*']);
        $opening = $rows->filter(fn ($i) => (bool) $i->opening_stock_recorded)->count();
        $zero = $rows->filter(fn ($i) => $this->compare($i->current_stock, '0') === 0)->count();
        $low = $rows->filter(fn ($i) => $this->compare($i->current_stock, '0') > 0 && $this->compare($i->current_stock, $i->minimum_stock_level) <= 0)->count();

        return response()->json(['data' => ['total_assigned_materials' => $rows->count(), 'materials_with_opening_stock' => $opening, 'materials_without_opening_stock' => $rows->count() - $opening, 'materials_below_minimum_stock' => $low, 'materials_with_zero_stock' => $zero]]);
    }

    private function project(Request $r, TenantContext $t, int $id): ?Project
    {
        $p = Project::query()->forCompany($t->companyId())->find($id);

        return $p && $r->user()->can('view', $p) ? $p : null;
    }

    private function inventory(Request $r, TenantContext $t, int $projectId, int $inventoryId): ?ProjectInventory
    {
        if (! $this->project($r, $t, $projectId)) {
            return null;
        }$i = ProjectInventory::query()->forCompany($t->companyId())->where('project_id', $projectId)->find($inventoryId);

        return $i && $r->user()->can('view', $i) ? $i : null;
    }

    private function balanced($q)
    {
        return $q->select('project_inventories.*')->selectRaw('('.$this->balanceSql().') as current_stock')->withExists(['transactions as opening_stock_recorded' => fn ($x) => $x->where('transaction_type', 'opening_stock')]);
    }

    private function balanceSql(): string
    {
        return "select coalesce(sum(case when direction = 'in' then quantity else -quantity end), 0) from inventory_transactions where inventory_transactions.project_inventory_id = project_inventories.id";
    }

    private function relations(bool $details = false): array
    {
        $r = ['material.category', 'material.measurementUnit'];
        if ($details) {
            $r[] = 'project:id,name,code';
        }if ($details) {
            $r[] = 'creator:id,name';
        }

        return $r;
    }

    private function load(ProjectInventory $i): ProjectInventory
    {
        return $i->load($this->relations());
    }

    private function duplicate(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['23000', '19'], true);
    }

    private function compare(mixed $a, mixed $b): int
    {
        [$ai,$af] = $this->decimalParts((string) $a);
        [$bi,$bf] = $this->decimalParts((string) $b);
        $ai = ltrim($ai, '0') ?: '0';
        $bi = ltrim($bi, '0') ?: '0';

        return strlen($ai) <=> strlen($bi) ?: strcmp($ai, $bi) ?: strcmp($af, $bf);
    }

    private function decimalParts(string $v): array
    {
        $p = explode('.', $v, 2);

        return [$p[0], str_pad(substr($p[1] ?? '', 0, 4), 4, '0')];
    }

    private function log(Request $r, ProjectInventory $i, string $e, array $c): void
    {
        activity('inventory')->causedBy($r->user())->performedOn($i)->event($e)->withProperties(['company_id' => $i->company_id, 'project_id' => $i->project_id, 'material_id' => $i->material_id, 'inventory_id' => $i->id, 'changes' => $c])->log($e);
    }

    private function denied(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Project inventory not found.'], 404);
    }

    private function conflict(string $m): JsonResponse
    {
        return response()->json(['message' => $m], 409);
    }
}
