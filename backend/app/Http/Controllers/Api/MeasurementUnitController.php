<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListCatalogRequest;
use App\Http\Requests\Api\V1\StoreMeasurementUnitRequest;
use App\Http\Requests\Api\V1\UpdateMeasurementUnitRequest;
use App\Http\Resources\Api\V1\MeasurementUnitResource;
use App\Models\MeasurementUnit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeasurementUnitController extends Controller
{
    public function index(ListCatalogRequest $r, TenantContext $t): JsonResponse
    {
        if (! $r->user()->can('materials.view')) {
            return $this->denied();
        } $v = $r->validated();
        $q = MeasurementUnit::query()->availableTo($t->companyId());
        if ($s = $v['search'] ?? null) {
            $q->where(fn ($x) => $x->where('name', 'like', "%{$s}%")->orWhere('symbol', 'like', "%{$s}%"));
        }if (array_key_exists('is_active', $v)) {
            $q->where('is_active', $v['is_active']);
        }$p = $q->orderByDesc('is_system')->orderBy('name')->paginate($v['per_page'] ?? 15);

        return $this->page($p, MeasurementUnitResource::class);
    }

    public function store(StoreMeasurementUnitRequest $r, TenantContext $t): JsonResponse
    {
        if (! $this->manage($r)) {
            return $this->denied();
        }$u = new MeasurementUnit($r->validated());
        $u->company_id = $t->companyId();
        $u->is_system = false;
        $u->save();
        $this->log($r, $u, 'measurement_unit.created', $u->only(['name', 'symbol', 'unit_type', 'decimal_precision', 'is_active']));

        return response()->json(['data' => new MeasurementUnitResource($u), 'message' => 'Measurement unit created successfully.'], 201);
    }

    public function show(Request $r, TenantContext $t, int $id): JsonResponse
    {
        if (! $r->user()->can('materials.view')) {
            return $this->denied();
        }$u = $this->find($t, $id);

        return $u ? response()->json(['data' => new MeasurementUnitResource($u)]) : $this->missing();
    }

    public function update(UpdateMeasurementUnitRequest $r, TenantContext $t, int $id): JsonResponse
    {
        $u = $this->find($t, $id);
        if (! $u) {
            return $this->missing();
        }if (! $this->manage($r) || $u->is_system) {
            return $this->denied();
        }$before = $u->only(array_keys($r->validated()));
        $u->update($r->validated());
        $this->log($r, $u, 'measurement_unit.updated', ['before' => $before, 'after' => $u->only(array_keys($r->validated()))]);

        return response()->json(['data' => new MeasurementUnitResource($u), 'message' => 'Measurement unit updated successfully.']);
    }

    public function destroy(Request $r, TenantContext $t, int $id): JsonResponse
    {
        $u = $this->find($t, $id);
        if (! $u) {
            return $this->missing();
        }if (! $this->manage($r) || $u->is_system) {
            return $this->denied();
        }$this->log($r, $u, 'measurement_unit.deleted', ['materials_preserved' => $u->materials()->count()]);
        $u->delete();

        return response()->json(['message' => 'Measurement unit deleted successfully.']);
    }

    private function find(TenantContext $t, int $id): ?MeasurementUnit
    {
        return MeasurementUnit::query()->availableTo($t->companyId())->find($id);
    }

    private function manage(Request $r): bool
    {
        return $r->user()->can('measurement_units.manage')
            && ! $r->user()->hasAnyRole(['site_supervisor', 'client']);
    }

    private function log(Request $r, MeasurementUnit $u, string $e, array $c): void
    {
        activity('measurement_units')->causedBy($r->user())->performedOn($u)->event($e)->withProperties(['company_id' => $r->user()->company_id, 'measurement_unit_id' => $u->id, 'changes' => $c])->log($e);
    }

    private function denied(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Measurement unit not found.'], 404);
    }

    private function page($p, string $resource): JsonResponse
    {
        return response()->json(['data' => $resource::collection($p->items()), 'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'per_page' => $p->perPage(), 'total' => $p->total()]]);
    }
}
