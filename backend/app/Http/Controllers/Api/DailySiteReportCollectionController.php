<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListDailySiteReportCollectionRequest;
use App\Http\Resources\Api\V1\DailySiteReportPreviewResource;
use App\Models\DailySiteReport;
use App\Models\Project;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class DailySiteReportCollectionController extends Controller
{
    public function __invoke(ListDailySiteReportCollectionRequest $request, TenantContext $tenant): JsonResponse
    {
        $user = $request->user();
        $companyId = $tenant->companyId();
        $filters = $request->validated();
        $projectId = $filters['project_id'] ?? null;

        if ($projectId) {
            abort_unless(Project::query()->forCompany($companyId)->accessibleTo($user)->whereKey($projectId)->exists(), 404);
        }

        // Scope both report ownership and project access before counting or pagination.
        $query = DailySiteReport::query()->forCompany($companyId)
            ->whereHas('project', fn (Builder $projects) => $projects->forCompany($companyId)->accessibleTo($user))
            ->with(['project:id,name,code', 'creator:id,name'])
            ->select(['id', 'company_id', 'project_id', 'created_by', 'title', 'report_date', 'work_completed', 'work_in_progress', 'total_workers_present', 'status']);

        // Match the existing project-nested collection's client visibility rules.
        if ($user->hasRole('client')) {
            $query->where('status', 'approved')->where('is_client_visible', true);
        }
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where('title', 'like', "%{$search}%");
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($from = $filters['date_from'] ?? null) {
            $query->whereDate('report_date', '>=', $from);
        }
        if ($to = $filters['date_to'] ?? null) {
            $query->whereDate('report_date', '<=', $to);
        }

        $page = $query->orderByDesc('report_date')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 4)->withQueryString();

        return response()->json([
            'data' => DailySiteReportPreviewResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total(),
                'from' => $page->firstItem(), 'to' => $page->lastItem(),
            ],
        ]);
    }
}
