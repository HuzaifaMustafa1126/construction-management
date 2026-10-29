<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\Project;
use App\Models\ProjectInventory;
use App\Support\Inventory\DecimalQuantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MaterialRequestController extends Controller
{
    public function index(Request $r, TenantContext $t): JsonResponse
    {
        if (! $r->user()->can('material_requests.view')) {
            return $this->deny();
        }$q = MaterialRequest::forCompany($t->companyId())->with(['project:id,name,code', 'requester:id,name'])->withCount('items');
        if (! $r->user()->hasRole('company_admin')) {
            $q->whereHas('project.members', fn ($x) => $x->where('users.id', $r->user()->id));
        }foreach (['project_id', 'status'] as $k) {
            if ($r->filled($k)) {
                $q->where($k, $r->input($k));
            }
        }$p = $q->latest()->paginate(min(100, max(1, (int) $r->input('per_page', 15))));

        return response()->json(['data' => $p->items(), 'meta' => $this->meta($p)]);
    }

    public function store(Request $r, TenantContext $t): JsonResponse
    {
        if (! $r->user()->can('material_requests.create')) {
            return $this->deny();
        }$v = $this->validateContent($r, $t);
        if ($v instanceof JsonResponse) {
            return $v;
        }$p = $this->project($r, $t, (int) $v['project_id']);
        if (! $p) {
            return $this->missing();
        }$m = DB::transaction(function () use ($r, $t, $v, $p) {
            $m = MaterialRequest::forceCreate(['company_id' => $t->companyId(), 'project_id' => $p->id, 'request_number' => 'MR-'.now()->format('Ymd').'-'.Str::upper((string) Str::ulid()), 'required_date' => $v['required_date'], 'reason' => $v['reason'] ?? null, 'status' => 'draft', 'requested_by' => $r->user()->id]);
            $this->replace($m, $v['items'], $t->companyId(), $p->id);

            return $m;
        });

        return response()->json(['data' => $this->data($m), 'message' => 'Material request created.'], 201);
    }

    public function show(Request $r, TenantContext $t, int $requestId): JsonResponse
    {
        $m = $this->find($r, $t, $requestId);

        return $m ? response()->json(['data' => $this->data($m)]) : $this->missing();
    }

    public function update(Request $r, TenantContext $t, int $requestId): JsonResponse
    {
        $m = $this->find($r, $t, $requestId);
        if (! $m) {
            return $this->missing();
        }if (! $r->user()->can('material_requests.update') || $m->status !== 'draft' || ! $this->ownsRequestOrIsCompanyAdmin($r, $m)) {
            return $this->deny();
        }$v = $this->validateContent($r, $t, $m->project_id, false);
        if ($v instanceof JsonResponse) {
            return $v;
        }DB::transaction(function () use ($m, $v, $t) {
            $m->update(['required_date' => $v['required_date'], 'reason' => $v['reason'] ?? null]);
            $this->replace($m, $v['items'], $t->companyId(), $m->project_id);
        });

        return response()->json(['data' => $this->data($m), 'message' => 'Material request updated.']);
    }

    public function submit(Request $r, TenantContext $t, int $requestId): JsonResponse
    {
        $m = $this->find($r, $t, $requestId);
        if (! $m) {
            return $this->missing();
        }if (! $r->user()->can('material_requests.submit') || $m->status !== 'draft' || ! $this->ownsRequestOrIsCompanyAdmin($r, $m)) {
            return $this->deny();
        }$m->forceFill(['status' => 'submitted', 'submitted_at' => now()])->save();

        return response()->json(['data' => $this->data($m), 'message' => 'Material request submitted.']);
    }

    public function approve(Request $r, TenantContext $t, int $requestId): JsonResponse
    {
        $m = $this->find($r, $t, $requestId);
        if (! $m) {
            return $this->missing();
        }if (! $r->user()->can('material_requests.approve') || $m->status !== 'submitted') {
            return $this->deny();
        }$v = Validator::make($r->all(), ['items' => 'required|array|min:1', 'items.*.id' => 'required|integer|distinct', 'items.*.quantity_approved' => 'required|decimal:0,4|min:0'])->validate();
        DB::transaction(function () use ($m, $v, $r) {
            $items = $m->items()->lockForUpdate()->get()->keyBy('id');
            foreach ($v['items'] as $x) {
                $i = $items->get($x['id']);
                if (! $i || DecimalQuantity::compare((string) $x['quantity_approved'], (string) $i->quantity_requested) > 0) {
                    abort(422, 'Approved quantity exceeds requested quantity.');
                }$i->forceFill(['quantity_approved' => $x['quantity_approved']])->save();
            }$m->forceFill(['status' => 'approved', 'approved_by' => $r->user()->id, 'approved_at' => now()])->save();
        });

        return response()->json(['data' => $this->data($m), 'message' => 'Material request approved.']);
    }

    public function reject(Request $r, TenantContext $t, int $requestId): JsonResponse
    {
        $m = $this->find($r, $t, $requestId);
        if (! $m) {
            return $this->missing();
        }if (! $r->user()->can('material_requests.approve') || $m->status !== 'submitted') {
            return $this->deny();
        }$v = $r->validate(['reason' => 'required|string|max:5000']);
        $m->forceFill(['status' => 'rejected', 'rejection_reason' => $v['reason'], 'approved_by' => $r->user()->id, 'approved_at' => now()])->save();

        return response()->json(['data' => $this->data($m)]);
    }

    public function cancel(Request $r, TenantContext $t, int $requestId): JsonResponse
    {
        $m = $this->find($r, $t, $requestId);
        if (! $m) {
            return $this->missing();
        }if (! $r->user()->can('material_requests.update') || ! $this->ownsRequestOrIsCompanyAdmin($r, $m)) {
            return $this->deny();
        }

        $cancelled = DB::transaction(function () use ($requestId, $t): ?MaterialRequest {
            $locked = MaterialRequest::forCompany($t->companyId())
                ->whereKey($requestId)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! in_array($locked->status, ['draft', 'submitted'], true)) {
                return null;
            }

            $locked->forceFill(['status' => 'cancelled'])->save();

            return $locked;
        });

        if (! $cancelled) {
            return $this->deny();
        }

        return response()->json(['data' => $this->data($cancelled)]);
    }

    public function issue(Request $r, TenantContext $t, int $requestId): JsonResponse
    {
        $m = $this->find($r, $t, $requestId);
        if (! $m) {
            return $this->missing();
        }if (! $r->user()->can('material_requests.issue') || ! in_array($m->status, ['approved', 'partially_issued'], true)) {
            return $this->deny();
        }$v = $r->validate(['idempotency_key' => 'required|uuid', 'issue_date' => 'required|date', 'items' => 'required|array|min:1', 'items.*.id' => 'required|integer|distinct', 'items.*.quantity' => 'required|decimal:0,4|min:0.0001', 'notes' => 'nullable|string|max:5000']);
        if (InventoryTransaction::where('company_id', $t->companyId())->where('idempotency_key', 'like', $v['idempotency_key'].'-%')->exists()) {
            return response()->json(['data' => $this->data($m), 'message' => 'Issue already posted.']);
        }DB::transaction(function () use ($m, $v, $r, $t) {
            $items = $m->items()->whereIn('id', collect($v['items'])->pluck('id'))->orderBy('project_inventory_id')->lockForUpdate()->get()->keyBy('id');
            $inventories = ProjectInventory::whereIn('id', $items->pluck('project_inventory_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($v['items'] as $x) {
                $i = $items->get($x['id']);
                if (! $i) {
                    abort(422, 'Invalid request item.');
                }$remaining = DecimalQuantity::add((string) $i->quantity_approved, '-'.(string) $i->quantity_issued);
                if (DecimalQuantity::compare((string) $x['quantity'], $remaining) > 0) {
                    abort(422, 'Issue exceeds approved remaining quantity.');
                }$balance = $this->balance($i->project_inventory_id);
                if (DecimalQuantity::compare($balance, (string) $x['quantity']) < 0) {
                    abort(422, 'Insufficient stock.');
                }InventoryTransaction::forceCreate(['company_id' => $t->companyId(), 'project_id' => $m->project_id, 'project_inventory_id' => $i->project_inventory_id, 'material_id' => $i->material_id, 'material_request_item_id' => $i->id, 'transaction_type' => 'material_issued', 'quantity' => $x['quantity'], 'direction' => 'out', 'reference_type' => MaterialRequestItem::class, 'reference_id' => $i->id, 'idempotency_key' => $v['idempotency_key'].'-'.$i->id, 'transaction_date' => $v['issue_date'], 'notes' => $v['notes'] ?? null, 'created_by' => $r->user()->id]);
                $i->forceFill(['quantity_issued' => DecimalQuantity::add((string) $i->quantity_issued, (string) $x['quantity'])])->save();
            }$m->refresh();
            $complete = $m->items()->whereRaw('quantity_issued < quantity_approved')->doesntExist();
            $m->forceFill(['status' => $complete ? 'issued' : 'partially_issued'])->save();
        });

        return response()->json(['data' => $this->data($m), 'message' => 'Materials issued.']);
    }

    public function returnStock(Request $r, TenantContext $t, int $requestId): JsonResponse
    {
        $m = $this->find($r, $t, $requestId);
        if (! $m) {
            return $this->missing();
        }
        if (! $r->user()->can('material_requests.return') || ! in_array($m->status, ['partially_issued', 'issued'], true)) {
            return $this->deny();
        }
        $v = $r->validate(['idempotency_key' => 'required|uuid', 'return_date' => 'required|date', 'item_id' => 'required|integer', 'quantity' => 'required|decimal:0,4|min:0.0001', 'notes' => 'nullable|string|max:5000']);
        $key = $v['idempotency_key'].'-'.$v['item_id'];
        if (InventoryTransaction::where('company_id', $t->companyId())->where('idempotency_key', $key)->exists()) {
            return response()->json(['data' => $this->data($m), 'message' => 'Return already posted.']);
        }
        DB::transaction(function () use ($m, $v, $r, $t, $key) {
            $i = $m->items()->whereKey($v['item_id'])->lockForUpdate()->firstOrFail();
            $eligible = DecimalQuantity::add((string) $i->quantity_issued, '-'.(string) $i->quantity_returned);
            if (DecimalQuantity::compare((string) $v['quantity'], $eligible) > 0) {
                abort(422, 'Return exceeds unreturned issued quantity.');
            }
            ProjectInventory::whereKey($i->project_inventory_id)->lockForUpdate()->firstOrFail();
            InventoryTransaction::forceCreate(['company_id' => $t->companyId(), 'project_id' => $m->project_id, 'project_inventory_id' => $i->project_inventory_id, 'material_id' => $i->material_id, 'material_request_item_id' => $i->id, 'transaction_type' => 'material_returned', 'quantity' => $v['quantity'], 'direction' => 'in', 'reference_type' => MaterialRequestItem::class, 'reference_id' => $i->id, 'idempotency_key' => $key, 'transaction_date' => $v['return_date'], 'notes' => $v['notes'] ?? null, 'created_by' => $r->user()->id]);
            $i->forceFill(['quantity_returned' => DecimalQuantity::add((string) $i->quantity_returned, (string) $v['quantity'])])->save();
        });

        return response()->json(['data' => $this->data($m), 'message' => 'Unused material returned to project stock.']);
    }

    private function validateContent(Request $r, TenantContext $t, ?int $projectId = null, bool $project = true): array|JsonResponse
    {
        $rules = ['required_date' => 'required|date', 'reason' => 'nullable|string|max:5000', 'items' => 'required|array|min:1|max:100', 'items.*.project_inventory_id' => 'required|integer|distinct', 'items.*.quantity_requested' => 'required|decimal:0,4|min:0.0001', 'items.*.notes' => 'nullable|string|max:5000'];
        if ($project) {
            $rules['project_id'] = 'required|integer';
        }$v = Validator::make($r->all(), $rules)->validate();
        $pid = $project ? (int) $v['project_id'] : $projectId;
        foreach ($v['items'] as $x) {
            if (! ProjectInventory::forCompany($t->companyId())->where('project_id', $pid)->whereKey($x['project_inventory_id'])->exists()) {
                return response()->json(['message' => 'Invalid project inventory item.'], 422);
            }
        }

        return $v;
    }

    private function replace(MaterialRequest $m, array $rows, int $c, int $p): void
    {
        $m->items()->delete();
        foreach ($rows as $x) {
            $i = ProjectInventory::forCompany($c)->where('project_id', $p)->findOrFail($x['project_inventory_id']);
            MaterialRequestItem::forceCreate(['company_id' => $c, 'material_request_id' => $m->id, 'project_inventory_id' => $i->id, 'material_id' => $i->material_id, 'quantity_requested' => $x['quantity_requested'], 'notes' => $x['notes'] ?? null]);
        }
    }

    private function project(Request $r, TenantContext $t, int $id): ?Project
    {
        $p = Project::forCompany($t->companyId())->find($id);

        return $p && $r->user()->can('view', $p) ? $p : null;
    }

    private function find(Request $r, TenantContext $t, int $id): ?MaterialRequest
    {
        if (! $r->user()->can('material_requests.view')) {
            return null;
        }$m = MaterialRequest::forCompany($t->companyId())->find($id);

        return $m && $this->project($r, $t, $m->project_id) ? $m : null;
    }

    private function ownsRequestOrIsCompanyAdmin(Request $request, MaterialRequest $materialRequest): bool
    {
        return $request->user()->hasRole('company_admin')
            || ($materialRequest->requested_by !== null
                && (int) $materialRequest->requested_by === (int) $request->user()->id);
    }

    private function data(MaterialRequest $m): MaterialRequest
    {
        return $m->load(['project:id,name,code', 'requester:id,name', 'approver:id,name', 'items.material.measurementUnit', 'items.inventory']);
    }

    private function balance(int $id): string
    {
        return (string) InventoryTransaction::where('project_inventory_id', $id)->selectRaw("coalesce(sum(case when direction='in' then quantity else -quantity end),0) b")->value('b');
    }

    private function meta($p): array
    {
        return ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'per_page' => $p->perPage(), 'total' => $p->total()];
    }

    private function deny(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Material request not found.'], 404);
    }
}
