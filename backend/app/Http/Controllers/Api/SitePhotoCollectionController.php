<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListSitePhotoCollectionRequest;
use App\Http\Resources\Api\V1\SitePhotoPreviewResource;
use App\Models\Project;
use App\Models\SitePhoto;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class SitePhotoCollectionController extends Controller
{
    public function __invoke(ListSitePhotoCollectionRequest $request, TenantContext $tenant): JsonResponse
    {
        $user = $request->user();
        $companyId = $tenant->companyId();
        $filters = $request->validated();
        $projectId = $filters['project_id'] ?? null;

        if ($projectId) {
            abort_unless(Project::query()->forCompany($companyId)->accessibleTo($user)->whereKey($projectId)->exists(), 404);
        }

        // Scope both photo ownership and project access before counting or pagination.
        $query = SitePhoto::query()->forCompany($companyId)
            ->whereHas('project', fn (Builder $projects) => $projects->forCompany($companyId)->accessibleTo($user))
            ->with('project:id,name,code')
            ->select(['id', 'company_id', 'project_id', 'caption', 'photo_date', 'time_category']);

        // Match the existing project-nested collection's client visibility rules.
        if ($user->hasRole('client')) {
            $query->where('approval_status', 'approved')->where('is_client_visible', true);
        }
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        if ($from = $filters['date_from'] ?? null) {
            $query->whereDate('photo_date', '>=', $from);
        }
        if ($to = $filters['date_to'] ?? null) {
            $query->whereDate('photo_date', '<=', $to);
        }

        $page = $query->orderByDesc('photo_date')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 3)->withQueryString();

        return response()->json([
            'data' => SitePhotoPreviewResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total(),
                'from' => $page->firstItem(), 'to' => $page->lastItem(),
            ],
        ]);
    }
}
