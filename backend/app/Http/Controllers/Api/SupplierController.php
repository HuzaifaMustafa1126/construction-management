<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListSuppliersRequest;
use App\Http\Requests\Api\V1\StoreSupplierRequest;
use App\Http\Requests\Api\V1\UpdateActiveStatusRequest;
use App\Http\Requests\Api\V1\UpdateSupplierRequest;
use App\Http\Resources\Api\V1\SupplierResource;
use App\Models\Supplier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(ListSuppliersRequest $r, TenantContext $t): JsonResponse
    {
        if (! $r->user()->can('suppliers.view') || $r->user()->hasRole('client')) {
            return $this->denied();
        }$v = $r->validated();
        $q = Supplier::query()->forCompany($t->companyId());
        if ($s = $v['search'] ?? null) {
            $q->where(fn ($x) => $x->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")->orWhere('contact_person', 'like', "%{$s}%"));
        }if (array_key_exists('is_active', $v)) {
            $q->where('is_active', $v['is_active']);
        }$p = $q->orderBy($v['sort'] ?? 'name', $v['direction'] ?? 'asc')->paginate($v['per_page'] ?? 15);

        return response()->json(['data' => SupplierResource::collection($p->items()), 'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'per_page' => $p->perPage(), 'total' => $p->total()]]);
    }

    public function store(StoreSupplierRequest $r, TenantContext $t): JsonResponse
    {
        if (! $this->manage($r)) {
            return $this->denied();
        }$s = new Supplier($r->validated());
        $s->company_id = $t->companyId();
        $s->created_by = $r->user()->id;
        $s->save();
        $this->log($r, $s, 'supplier.created', $s->only(['name', 'contact_person', 'phone', 'email', 'is_active']));

        return response()->json(['data' => new SupplierResource($s), 'message' => 'Supplier created successfully.'], 201);
    }

    public function show(Request $r, TenantContext $t, int $id): JsonResponse
    {
        if (! $r->user()->can('suppliers.view')) {
            return $this->denied();
        }$s = $this->find($t, $id);

        return $s && $r->user()->can('view', $s) ? response()->json(['data' => new SupplierResource($s)]) : $this->missing();
    }

    public function update(UpdateSupplierRequest $r, TenantContext $t, int $id): JsonResponse
    {
        $s = $this->find($t, $id);
        if (! $s) {
            return $this->missing();
        }if (! $this->manage($r) || ! $r->user()->can('manage', $s)) {
            return $this->denied();
        }$before = $s->only(array_keys($r->validated()));
        $s->update($r->validated());
        $this->log($r, $s, 'supplier.updated', ['before' => $before, 'after' => $s->only(array_keys($r->validated()))]);

        return response()->json(['data' => new SupplierResource($s), 'message' => 'Supplier updated successfully.']);
    }

    public function status(UpdateActiveStatusRequest $r, TenantContext $t, int $id): JsonResponse
    {
        $s = $this->find($t, $id);
        if (! $s) {
            return $this->missing();
        }if (! $this->manage($r)) {
            return $this->denied();
        }$before = $s->is_active;
        $s->update($r->validated());
        $this->log($r, $s, 'supplier.status_changed', ['before' => $before, 'after' => $s->is_active]);

        return response()->json(['data' => new SupplierResource($s), 'message' => 'Supplier status updated successfully.']);
    }

    public function destroy(Request $r, TenantContext $t, int $id): JsonResponse
    {
        $s = $this->find($t, $id);
        if (! $s) {
            return $this->missing();
        }if (! $this->manage($r)) {
            return $this->denied();
        }$this->log($r, $s, 'supplier.deleted', ['deliveries_preserved' => $s->deliveries()->count()]);
        $s->delete();

        return response()->json(['message' => 'Supplier deleted successfully.']);
    }

    private function find(TenantContext $t, int $id): ?Supplier
    {
        return Supplier::query()->forCompany($t->companyId())->find($id);
    }

    private function manage(Request $r): bool
    {
        return $r->user()->can('suppliers.manage') && ! $r->user()->hasAnyRole(['site_supervisor', 'client']);
    }

    private function log(Request $r, Supplier $s, string $e, array $c): void
    {
        activity('suppliers')->causedBy($r->user())->performedOn($s)->event($e)->withProperties(['company_id' => $s->company_id, 'supplier_id' => $s->id, 'changes' => $c])->log($e);
    }

    private function denied(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Supplier not found.'], 404);
    }
}
