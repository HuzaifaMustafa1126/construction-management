<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use App\Models\Project;
use App\Models\ProjectInventory;
use App\Support\Inventory\DecimalQuantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryTransferController extends Controller
{
    public function store(Request $r, TenantContext $t): JsonResponse
    {
        if (! $r->user()->can('inventory.transfer')) {
            return response()->json(['message' => 'You do not have permission to transfer stock.'], 403);
        }$v = $r->validate(['idempotency_key' => 'required|uuid', 'source_project_id' => 'required|integer|different:destination_project_id', 'destination_project_id' => 'required|integer', 'source_inventory_id' => 'required|integer', 'destination_inventory_id' => 'required|integer|different:source_inventory_id', 'quantity' => 'required|decimal:0,4|min:0.0001', 'transfer_date' => 'required|date', 'notes' => 'nullable|string|max:5000']);
        $projects = Project::forCompany($t->companyId())->whereIn('id', [$v['source_project_id'], $v['destination_project_id']])->get()->keyBy('id');
        if ($projects->count() !== 2 || $projects->contains(fn ($p) => ! $r->user()->can('view', $p))) {
            return response()->json(['message' => 'Project not found.'], 404);
        }$existing = DB::table('inventory_transfers')->where('company_id', $t->companyId())->where('idempotency_key', $v['idempotency_key'])->first();
        if ($existing) {
            return response()->json(['data' => $existing, 'message' => 'Transfer already posted.']);
        }$transfer = DB::transaction(function () use ($r, $t, $v) {
            $ids = collect([$v['source_inventory_id'], $v['destination_inventory_id']])->sort()->values();
            $rows = ProjectInventory::forCompany($t->companyId())->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $rows->get($v['source_inventory_id']);
            $destination = $rows->get($v['destination_inventory_id']);
            if (! $source || ! $destination || $source->project_id != $v['source_project_id'] || $destination->project_id != $v['destination_project_id'] || $source->material_id != $destination->material_id) {
                abort(422, 'Invalid transfer inventory mapping.');
            }$balance = (string) InventoryTransaction::where('project_inventory_id', $source->id)->selectRaw("coalesce(sum(case when direction='in' then quantity else -quantity end),0) b")->value('b');
            if (DecimalQuantity::compare($balance, (string) $v['quantity']) < 0) {
                abort(422, 'Insufficient stock.');
            }$transferId = DB::table('inventory_transfers')->insertGetId(['idempotency_key' => $v['idempotency_key'], 'company_id' => $t->companyId(), 'source_project_id' => $source->project_id, 'destination_project_id' => $destination->project_id, 'source_inventory_id' => $source->id, 'destination_inventory_id' => $destination->id, 'material_id' => $source->material_id, 'quantity' => $v['quantity'], 'transfer_date' => $v['transfer_date'], 'notes' => $v['notes'] ?? null, 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $out = InventoryTransaction::forceCreate(['company_id' => $t->companyId(), 'project_id' => $source->project_id, 'project_inventory_id' => $source->id, 'material_id' => $source->material_id, 'transaction_type' => 'transfer_out', 'quantity' => $v['quantity'], 'direction' => 'out', 'reference_type' => 'inventory_transfer', 'reference_id' => $transferId, 'idempotency_key' => $v['idempotency_key'].'-out', 'transaction_date' => $v['transfer_date'], 'notes' => $v['notes'] ?? null, 'created_by' => $r->user()->id]);
            $in = InventoryTransaction::forceCreate(['company_id' => $t->companyId(), 'project_id' => $destination->project_id, 'project_inventory_id' => $destination->id, 'material_id' => $destination->material_id, 'transaction_type' => 'transfer_in', 'quantity' => $v['quantity'], 'direction' => 'in', 'reference_type' => 'inventory_transfer', 'reference_id' => $transferId, 'related_transaction_id' => $out->id, 'idempotency_key' => $v['idempotency_key'].'-in', 'transaction_date' => $v['transfer_date'], 'notes' => $v['notes'] ?? null, 'created_by' => $r->user()->id]);
            $out->forceFill(['related_transaction_id' => $in->id])->save();

            return DB::table('inventory_transfers')
                ->where('company_id', $t->companyId())
                ->where('id', $transferId)
                ->first();
        });

        return response()->json(['data' => $transfer, 'message' => 'Inventory transferred successfully.'], 201);
    }
}
