<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListInventoryCollectionRequest;
use App\Http\Resources\Api\V1\InventoryStockPreviewResource;
use App\Models\Project;
use App\Services\InventoryReportingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventoryCollectionController extends Controller
{
    public function __invoke(ListInventoryCollectionRequest $request, TenantContext $tenant, InventoryReportingService $reports): JsonResponse
    {
        $filters = $request->validated();
        $projects = Project::query()->forCompany($tenant->companyId())->accessibleTo($request->user());
        if ($projectId = $filters['project_id'] ?? null) {
            abort_unless((clone $projects)->whereKey($projectId)->exists(), 404);
            $projects->whereKey($projectId);
        }

        // Reuse the authoritative ledger query with a scoped subquery, without loading project IDs.
        $stock = $reports->stockQuery($tenant->companyId(), $projects->select('projects.id'), []);
        $page = DB::query()->fromSub($stock, 'stock')
            ->orderByRaw('CASE WHEN current_stock < 0 THEN 0 WHEN current_stock = 0 THEN 1 WHEN minimum_stock_level > 0 AND current_stock <= minimum_stock_level THEN 2 ELSE 3 END')
            ->orderBy('material_name')->orderBy('project_id')->orderBy('inventory_id')
            ->paginate($filters['per_page'] ?? 4)->withQueryString();
        $rows = collect($page->items())->map(function ($row) use ($reports) {
            if ($row->current_stock === null || $row->minimum_stock_level === null) {
                $row->stock_status = 'unavailable';

                return $row;
            }

            return $reports->decorate(collect([$row]))->first();
        });

        return response()->json([
            'data' => InventoryStockPreviewResource::collection($rows),
            'meta' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total(),
            ],
        ]);
    }
}
