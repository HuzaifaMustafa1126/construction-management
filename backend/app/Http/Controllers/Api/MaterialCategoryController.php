<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListCatalogRequest;
use App\Http\Requests\Api\V1\StoreMaterialCategoryRequest;
use App\Http\Requests\Api\V1\UpdateActiveStatusRequest;
use App\Http\Requests\Api\V1\UpdateMaterialCategoryRequest;
use App\Http\Resources\Api\V1\MaterialCategoryResource;
use App\Models\MaterialCategory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialCategoryController extends Controller
{
    public function index(ListCatalogRequest $r, TenantContext $t): JsonResponse
    {
        if (! $r->user()->can('materials.view')) {
            return $this->denied();
        }$v = $r->validated();
        $q = MaterialCategory::query()->forCompany($t->companyId());
        if ($s = $v['search'] ?? null) {
            $q->where('name', 'like', "%{$s}%");
        }if (array_key_exists('is_active', $v)) {
            $q->where('is_active', $v['is_active']);
        }$p = $q->orderBy('name')->paginate($v['per_page'] ?? 15);

        return response()->json(['data' => MaterialCategoryResource::collection($p->items()), 'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'per_page' => $p->perPage(), 'total' => $p->total()]]);
    }

    public function store(StoreMaterialCategoryRequest $r, TenantContext $t): JsonResponse
    {
        if (! $this->manage($r)) {
            return $this->denied();
        }$c = new MaterialCategory($r->validated());
        $c->company_id = $t->companyId();
        $c->created_by = $r->user()->id;
        $c->save();
        $this->log($r, $c, 'material_category.created', $c->only(['name', 'description', 'is_active']));

        return response()->json(['data' => new MaterialCategoryResource($c), 'message' => 'Material category created successfully.'], 201);
    }

    public function show(Request $r, TenantContext $t, int $id): JsonResponse
    {
        if (! $r->user()->can('materials.view')) {
            return $this->denied();
        }$c = $this->find($t, $id);

        return $c ? response()->json(['data' => new MaterialCategoryResource($c)]) : $this->missing();
    }

    public function update(UpdateMaterialCategoryRequest $r, TenantContext $t, int $id): JsonResponse
    {
        $c = $this->find($t, $id);
        if (! $c) {
            return $this->missing();
        }if (! $this->manage($r)) {
            return $this->denied();
        }$before = $c->only(array_keys($r->validated()));
        $c->update($r->validated());
        $this->log($r, $c, 'material_category.updated', ['before' => $before, 'after' => $c->only(array_keys($r->validated()))]);

        return response()->json(['data' => new MaterialCategoryResource($c), 'message' => 'Material category updated successfully.']);
    }

    public function status(UpdateActiveStatusRequest $r, TenantContext $t, int $id): JsonResponse
    {
        $c = $this->find($t, $id);
        if (! $c) {
            return $this->missing();
        }if (! $this->manage($r)) {
            return $this->denied();
        }$before = $c->is_active;
        $c->update($r->validated());
        $this->log($r, $c, 'material_category.updated', ['is_active' => ['before' => $before, 'after' => $c->is_active]]);

        return response()->json(['data' => new MaterialCategoryResource($c), 'message' => 'Material category status updated successfully.']);
    }

    public function destroy(Request $r, TenantContext $t, int $id): JsonResponse
    {
        $c = $this->find($t, $id);
        if (! $c) {
            return $this->missing();
        }if (! $this->manage($r)) {
            return $this->denied();
        }$this->log($r, $c, 'material_category.deleted', ['materials_preserved' => $c->materials()->count()]);
        $c->delete();

        return response()->json(['message' => 'Material category deleted successfully.']);
    }

    private function find(TenantContext $t, int $id): ?MaterialCategory
    {
        return MaterialCategory::query()->forCompany($t->companyId())->find($id);
    }

    private function manage(Request $r): bool
    {
        return $r->user()->can('material_categories.manage')
            && ! $r->user()->hasAnyRole(['site_supervisor', 'client']);
    }

    private function log(Request $r, MaterialCategory $c, string $e, array $x): void
    {
        activity('material_categories')->causedBy($r->user())->performedOn($c)->event($e)->withProperties(['company_id' => $c->company_id, 'material_category_id' => $c->id, 'changes' => $x])->log($e);
    }

    private function denied(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Material category not found.'], 404);
    }
}
