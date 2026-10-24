<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InventoryReportRequest;
use App\Http\Resources\Api\V1\InventoryMovementReportResource;
use App\Http\Resources\Api\V1\InventoryStockReportResource;
use App\Models\Project;
use App\Services\InventoryReportingService;
use App\Support\Inventory\DecimalQuantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventoryReportController extends Controller
{
    public function __construct(private InventoryReportingService $reports) {}

    public function overview(InventoryReportRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $this->project($request, $tenant, $id)) {
            return $this->missing();
        }
        if (! $request->user()->can('inventory_reports.view')) {
            return $this->denied();
        }

        return response()->json(['data' => $this->overviewData($tenant->companyId(), [$id], $request->validated())]);
    }

    public function currentStock(InventoryReportRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $this->project($request, $tenant, $id)) {
            return $this->missing();
        }
        if (! $request->user()->can('inventory_reports.view')) {
            return $this->denied();
        }
        $validated = $request->validated();
        $sort = $validated['sort'] ?? 'material_name';
        $column = match ($sort) {
            'current_stock' => 'current_stock', default => 'material_name'
        };
        $page = $this->reports->stockQuery($tenant->companyId(), [$id], $validated)->orderBy($column, $validated['direction_sort'] ?? 'asc')->paginate($validated['per_page'] ?? 15);
        $page->setCollection($this->reports->decorate($page->getCollection()));

        return response()->json(['data' => InventoryStockReportResource::collection($page->items()), 'meta' => $this->meta($page)]);
    }

    public function alerts(InventoryReportRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $this->project($request, $tenant, $id)) {
            return $this->missing();
        }
        if (! $request->user()->can('inventory_reports.view_alerts')) {
            return $this->denied();
        }
        $rows = $this->reports->decorate($this->reports->stockQuery($tenant->companyId(), [$id], $request->validated())->orderBy('material_name')->get())->reject(fn ($row) => $row->stock_status === 'healthy')->values();
        $data = InventoryStockReportResource::collection($rows)->resolve($request);
        foreach ($data as &$row) {
            $row['alert_type'] = $row['stock_status'];
            $row['severity'] = $row['stock_status'] === 'low_stock' ? 'warning' : 'critical';
        }

        return response()->json(['data' => $data]);
    }

    public function movements(InventoryReportRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $this->project($request, $tenant, $id)) {
            return $this->missing();
        }
        if (! $request->user()->can('inventory_reports.view_movements')) {
            return $this->denied();
        }
        $validated = $request->validated();
        $page = $this->reports->movements($tenant->companyId(), [$id], $validated)->orderBy(($validated['sort'] ?? null) === 'created_at' ? 'created_at' : 'transaction_date', $validated['direction_sort'] ?? 'desc')->orderBy('id', 'desc')->paginate($validated['per_page'] ?? 15);

        return response()->json(['data' => InventoryMovementReportResource::collection($page->items()), 'meta' => $this->meta($page)]);
    }

    public function receivedVsConsumed(InventoryReportRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $this->project($request, $tenant, $id)) {
            return $this->missing();
        }
        if (! $request->user()->can('inventory_reports.view')) {
            return $this->denied();
        }
        $filters = $request->validated();
        $rows = $this->reports->decorate($this->reports->stockQuery($tenant->companyId(), [$id], $filters)->orderBy('material_name')->get());
        $period = $this->reports->periodTotals($tenant->companyId(), [$id], $filters)->groupBy('material_id');
        $data = $rows->map(function ($row) use ($period) {
            $totals = $period->get($row->material_id, collect())->pluck('quantity', 'transaction_type');
            $received = (string) ($totals['material_received'] ?? '0');
            $consumed = (string) ($totals['material_consumed'] ?? '0');
            $wastage = (string) ($totals['material_wastage'] ?? '0');
            $adjustment = '0.0000';
            foreach (['adjustment_in', 'inventory_correction_in'] as $type) {
                $adjustment = DecimalQuantity::add($adjustment, (string) ($totals[$type] ?? '0'));
            }
            foreach (['adjustment_out', 'inventory_correction_out'] as $type) {
                $adjustment = DecimalQuantity::add($adjustment, '-'.ltrim((string) ($totals[$type] ?? '0'), '+'));
            }
            $precision = (int) $row->decimal_precision;

            return ['material' => ['id' => $row->material_id, 'name' => $row->material_name, 'code' => $row->material_code], 'measurement_unit' => ['id' => $row->unit_id, 'name' => $row->unit_name, 'symbol' => $row->unit_symbol], 'received' => $this->decimal($received, $precision), 'consumed' => $this->decimal($consumed, $precision), 'difference' => $this->decimal(DecimalQuantity::add($received, '-'.ltrim($consumed, '+')), $precision), 'wastage' => $this->decimal($wastage, $precision), 'adjustment_movement' => $this->decimal($adjustment, $precision), 'current_stock' => $this->decimal($row->current_stock, $precision)];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function wastage(InventoryReportRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        if (! $this->project($request, $tenant, $id)) {
            return $this->missing();
        }
        if (! $request->user()->can('inventory_reports.view_movements')) {
            return $this->denied();
        }
        $filters = $request->validated();
        $query = DB::table('inventory_transactions as t')->join('material_stock_adjustment_items as i', 'i.id', '=', 't.stock_adjustment_item_id')->join('material_stock_adjustments as a', 'a.id', '=', 'i.material_stock_adjustment_id')->join('materials as m', 'm.id', '=', 't.material_id')->join('measurement_units as u', 'u.id', '=', 'm.measurement_unit_id')->join('users as actor', 'actor.id', '=', 't.created_by')->where('t.company_id', $tenant->companyId())->where('t.project_id', $id)->where('t.transaction_type', 'material_wastage')->where('a.status', 'posted');
        if ($value = $filters['start_date'] ?? null) {
            $query->whereDate('t.transaction_date', '>=', $value);
        }
        if ($value = $filters['end_date'] ?? null) {
            $query->whereDate('t.transaction_date', '<=', $value);
        }
        if ($value = $filters['material_id'] ?? null) {
            $query->where('t.material_id', $value);
        }
        if ($value = $filters['reason_code'] ?? null) {
            $query->where('i.reason_code', $value);
        }
        $breakdownQuery = clone $query;
        $page = $query->select(['t.id', 't.project_id', 't.material_id', 'm.name as material_name', 'm.code as material_code', 'u.name as unit_name', 'u.symbol as unit_symbol', 'u.decimal_precision', 't.quantity', 'i.reason_code', 'a.id as adjustment_id', 'a.adjustment_number', 'actor.id as actor_id', 'actor.name as actor_name', 'a.posted_at', 't.transaction_date'])->orderByDesc('t.transaction_date')->orderByDesc('t.id')->paginate($filters['per_page'] ?? 15);
        $data = collect($page->items())->map(fn ($row) => ['ledger_transaction_id' => $row->id, 'project_id' => $row->project_id, 'material' => ['id' => $row->material_id, 'name' => $row->material_name, 'code' => $row->material_code], 'measurement_unit' => ['name' => $row->unit_name, 'symbol' => $row->unit_symbol], 'quantity' => $this->decimal($row->quantity, (int) $row->decimal_precision), 'reason_code' => $row->reason_code, 'adjustment' => ['id' => $row->adjustment_id, 'number' => $row->adjustment_number], 'recorded_by' => ['id' => $row->actor_id, 'name' => $row->actor_name], 'movement_date' => $row->transaction_date, 'posted_at' => $row->posted_at]);
        $breakdown = $breakdownQuery->select(['i.reason_code', 'm.id as material_id', 'm.name as material_name', 'm.code as material_code', 'u.id as unit_id', 'u.name as unit_name', 'u.symbol as unit_symbol', 'u.decimal_precision'])->selectRaw('sum(t.quantity) quantity')->groupBy('i.reason_code', 'm.id', 'm.name', 'm.code', 'u.id', 'u.name', 'u.symbol', 'u.decimal_precision')->get()->map(fn ($row) => ['reason_code' => $row->reason_code, 'material' => ['id' => $row->material_id, 'name' => $row->material_name, 'code' => $row->material_code], 'measurement_unit' => ['id' => $row->unit_id, 'name' => $row->unit_name, 'symbol' => $row->unit_symbol], 'quantity' => $this->decimal($row->quantity, (int) $row->decimal_precision)]);

        return response()->json(['data' => $data, 'breakdown' => $breakdown, 'meta' => $this->meta($page)]);
    }

    public function companyOverview(InventoryReportRequest $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->can('inventory_reports.view_company')) {
            return $this->denied();
        }
        $projectIds = $this->reports->allowedProjectIds($request->user(), $tenant->companyId());

        return response()->json(['data' => $this->overviewData($tenant->companyId(), $projectIds->all(), $request->validated()) + ['total_projects_with_inventory' => DB::table('project_inventories')->where('company_id', $tenant->companyId())->whereIn('project_id', $projectIds)->whereNull('deleted_at')->distinct()->count('project_id')]]);
    }

    public function companyProjects(InventoryReportRequest $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->can('inventory_reports.view_company')) {
            return $this->denied();
        }
        $projectIds = $this->reports->allowedProjectIds($request->user(), $tenant->companyId());
        $rows = $this->reports->decorate($this->reports->stockQuery($tenant->companyId(), $projectIds->all(), $request->validated())->get());
        $data = $rows->groupBy('project_id')->map(function ($items) {
            $first = $items->first();

            return ['project' => ['id' => $first->project_id, 'name' => $first->project_name], 'inventory_item_count' => $items->count(), 'low_stock_count' => $items->where('stock_status', 'low_stock')->count(), 'out_of_stock_count' => $items->where('stock_status', 'out_of_stock')->count(), 'negative_stock_anomaly_count' => $items->where('stock_status', 'negative_stock_anomaly')->count(), 'recent_movement_date' => $items->max('last_movement_date')];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function companyAlerts(InventoryReportRequest $request, TenantContext $tenant): JsonResponse
    {
        if (! $request->user()->can('inventory_reports.view_company') || ! $request->user()->can('inventory_reports.view_alerts')) {
            return $this->denied();
        }
        $projectIds = $this->reports->allowedProjectIds($request->user(), $tenant->companyId());
        $rows = $this->reports->decorate($this->reports->stockQuery($tenant->companyId(), $projectIds->all(), $request->validated())->get())->reject(fn ($row) => $row->stock_status === 'healthy')->values();
        $data = InventoryStockReportResource::collection($rows)->resolve($request);
        foreach ($data as &$row) {
            $row['alert_type'] = $row['stock_status'];
            $row['severity'] = $row['stock_status'] === 'low_stock' ? 'warning' : 'critical';
        }

        return response()->json(['data' => $data]);
    }

    private function overviewData(int $companyId, array $projectIds, array $filters): array
    {
        if (! $projectIds) {
            return ['total_distinct_materials' => 0, 'total_active_inventory_items' => 0, 'low_stock_count' => 0, 'out_of_stock_count' => 0, 'healthy_count' => 0, 'negative_stock_anomaly_count' => 0, 'period_totals_by_material' => [], 'recent_movements' => []];
        }
        $rows = $this->reports->decorate($this->reports->stockQuery($companyId, $projectIds, $filters)->get());
        $totals = $this->reports->periodTotals($companyId, $projectIds, $filters)->groupBy('material_id')->map(fn ($items, $materialId) => ['material_id' => (int) $materialId, 'received' => (string) ($items->firstWhere('transaction_type', 'material_received')->quantity ?? '0'), 'consumed' => (string) ($items->firstWhere('transaction_type', 'material_consumed')->quantity ?? '0'), 'wastage' => (string) ($items->firstWhere('transaction_type', 'material_wastage')->quantity ?? '0'), 'adjustment_movements' => $items->whereIn('transaction_type', ['adjustment_in', 'adjustment_out', 'inventory_correction_in', 'inventory_correction_out'])->map(fn ($item) => ['type' => $item->transaction_type, 'quantity' => (string) $item->quantity])->values()])->values();
        $recent = $this->reports->movements($companyId, $projectIds, [])->orderByDesc('transaction_date')->orderByDesc('id')->limit(10)->get();

        return ['total_distinct_materials' => $rows->pluck('material_id')->unique()->count(), 'total_active_inventory_items' => $rows->count(), 'low_stock_count' => $rows->where('stock_status', 'low_stock')->count(), 'out_of_stock_count' => $rows->where('stock_status', 'out_of_stock')->count(), 'healthy_count' => $rows->where('stock_status', 'healthy')->count(), 'negative_stock_anomaly_count' => $rows->where('stock_status', 'negative_stock_anomaly')->count(), 'period_totals_by_material' => $totals, 'recent_movements' => InventoryMovementReportResource::collection($recent)->resolve()];
    }

    private function project(InventoryReportRequest $request, TenantContext $tenant, int $id): ?Project
    {
        $project = Project::query()->forCompany($tenant->companyId())->find($id);

        return $project && $request->user()->can('view', $project) ? $project : null;
    }

    private function decimal(mixed $value, int $precision): string
    {
        $parts = explode('.', (string) $value, 2);

        return $precision === 0 ? $parts[0] : $parts[0].'.'.str_pad(substr($parts[1] ?? '', 0, $precision), $precision, '0');
    }

    private function meta($page): array
    {
        return ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()];
    }

    private function denied(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Inventory report project not found.'], 404);
    }
}
