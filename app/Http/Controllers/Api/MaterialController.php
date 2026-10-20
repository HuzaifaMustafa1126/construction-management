<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListMaterialsRequest;
use App\Http\Requests\Api\V1\StoreMaterialRequest;
use App\Http\Requests\Api\V1\UpdateActiveStatusRequest;
use App\Http\Requests\Api\V1\UpdateMaterialRequest;
use App\Http\Resources\Api\V1\MaterialResource;
use App\Models\Material;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index(ListMaterialsRequest $r, TenantContext $t): JsonResponse
    {
        if (! $r->user()->can('materials.view') || $r->user()->hasRole('client')) {
            return $this->denied();
        }$v = $r->validated();
        $q = Material::query()->forCompany($t->companyId())->with(['category:id,name', 'measurementUnit:id,name,symbol']);
        if ($s = $v['search'] ?? null) {
            $q->where(fn ($x) => $x->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
        }if ($id = $v['material_category_id'] ?? null) {
            $q->where('material_category_id', $id);
        }if ($id = $v['measurement_unit_id'] ?? null) {
            $q->where('measurement_unit_id', $id);
        }if (array_key_exists('is_active', $v)) {
            $q->where('is_active', $v['is_active']);
        }$p = $q->orderBy($v['sort'] ?? 'name', $v['direction'] ?? 'asc')->paginate($v['per_page'] ?? 15);

        return response()->json(['data' => MaterialResource::collection($p->items()), 'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'per_page' => $p->perPage(), 'total' => $p->total()]]);
    }

    public function store(StoreMaterialRequest $r, TenantContext $t): JsonResponse
    {
        if (! $this->manage($r, 'materials.create')) {
            return $this->denied();
        }$m = new Material($r->validated());
        $m->company_id = $t->companyId();
        $m->created_by = $r->user()->id;
        $m->save();
        $this->log($r, $m, 'material.created', $m->only(['name', 'code', 'material_category_id', 'measurement_unit_id', 'description', 'specification', 'brand', 'is_active']));

        return response()->json(['data' => new MaterialResource($this->load($m)), 'message' => 'Material created successfully.'], 201);
    }

    public function show(Request $r, TenantContext $t, int $id): JsonResponse
    {
        if (! $r->user()->can('materials.view') || $r->user()->hasRole('client')) {
            return $this->denied();
        }$m = $this->find($t, $id);

        return $m ? response()->json(['data' => new MaterialResource($this->load($m))]) : $this->missing();
    }

    public function update(UpdateMaterialRequest $r, TenantContext $t, int $id): JsonResponse
    {
        $m = $this->find($t, $id);
        if (! $m) {
            return $this->missing();
        }if (! $this->manage($r, 'materials.update')) {
            return $this->denied();
        }$before = $m->only(array_keys($r->validated()));
        $m->update($r->validated());
        $this->log($r, $m, 'material.updated', ['before' => $before, 'after' => $m->only(array_keys($r->validated()))]);

        return response()->json(['data' => new MaterialResource($this->load($m)), 'message' => 'Material updated successfully.']);
    }

    public function status(UpdateActiveStatusRequest $r, TenantContext $t, int $id): JsonResponse
    {
        $m = $this->find($t, $id);
        if (! $m) {
            return $this->missing();
        }if (! $this->manage($r, 'materials.update')) {
            return $this->denied();
        }$before = $m->is_active;
        $m->update($r->validated());
        $this->log($r, $m, 'material.status_changed', ['before' => $before, 'after' => $m->is_active]);

        return response()->json(['data' => new MaterialResource($this->load($m)), 'message' => 'Material status updated successfully.']);
    }

    public function destroy(Request $r, TenantContext $t, int $id): JsonResponse
    {
        $m = $this->find($t, $id);
        if (! $m) {
            return $this->missing();
        }if (! $this->manage($r, 'materials.delete')) {
            return $this->denied();
        }$this->log($r, $m, 'material.deleted', ['code' => $m->code]);
        $m->delete();

        return response()->json(['message' => 'Material deleted successfully.']);
    }

    private function find(TenantContext $t, int $id): ?Material
    {
        return Material::query()->forCompany($t->companyId())->find($id);
    }

    private function load(Material $m): Material
    {
        return $m->load(['category:id,name', 'measurementUnit:id,name,symbol']);
    }

    private function manage(Request $r, string $permission): bool
    {
        return $r->user()->can($permission) && ! $r->user()->hasAnyRole(['site_supervisor', 'client']);
    }

    private function log(Request $r, Material $m, string $e, array $x): void
    {
        activity('materials')->causedBy($r->user())->performedOn($m)->event($e)->withProperties(['company_id' => $m->company_id, 'material_id' => $m->id, 'changes' => $x])->log($e);
    }

    private function denied(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Material not found.'], 404);
    }
}
