<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BatchStoreSitePhotoRequest;
use App\Http\Requests\Api\V1\ListSitePhotosRequest;
use App\Http\Requests\Api\V1\ReviewSitePhotoRequest;
use App\Http\Requests\Api\V1\StoreSitePhotoRequest;
use App\Http\Requests\Api\V1\UpdateSitePhotoRequest;
use App\Http\Requests\Api\V1\UpdateSitePhotoVisibilityRequest;
use App\Http\Resources\Api\V1\SitePhotoResource;
use App\Models\DailySiteReport;
use App\Models\Project;
use App\Models\SitePhoto;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SitePhotoController extends Controller
{
    private const DISK = 'local';

    public function index(ListSitePhotosRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->project($request, $tenant, $id);
        if (! $project) {
            return $this->notFound();
        }
        if (! $request->user()->can('photos.view')) {
            return $this->forbidden();
        }
        $v = $request->validated();
        $query = SitePhoto::query()->forCompany($tenant->companyId())->where('project_id', $project->id)->with('uploader:id,name');
        if ($request->user()->hasRole('client')) {
            $query->where('approval_status', 'approved')->where('is_client_visible', true);
        }
        if ($search = $v['search'] ?? null) {
            $query->where('caption', 'like', "%{$search}%");
        }
        if ($date = $v['date'] ?? null) {
            $query->whereDate('photo_date', $date);
        }
        if ($from = $v['date_from'] ?? null) {
            $query->whereDate('photo_date', '>=', $from);
        }
        if ($to = $v['date_to'] ?? null) {
            $query->whereDate('photo_date', '<=', $to);
        }
        if ($category = $v['time_category'] ?? null) {
            $query->where('time_category', $category);
        }
        if (! $request->user()->hasRole('client') && ($status = $v['approval_status'] ?? null)) {
            $query->where('approval_status', $status);
        }
        $page = $query->orderBy($v['sort'] ?? 'photo_date', $v['direction'] ?? 'desc')->paginate($v['per_page'] ?? 15)->withQueryString();

        return response()->json(['data' => SitePhotoResource::collection($page->items()), 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
        ]]);
    }

    public function store(StoreSitePhotoRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->project($request, $tenant, $id);
        if (! $project) {
            return $this->notFound();
        }
        if (! $request->user()->can('photos.create') || ! $request->user()->can('create', [SitePhoto::class, $project])) {
            return $this->forbidden();
        }
        $stored = [];
        try {
            $photo = DB::transaction(function () use ($request, $tenant, $project, &$stored): SitePhoto {
                $photo = $this->persist($request->file('photo'), $request->safe()->except(['photo']), $tenant->companyId(), $project->id, $request->user()->id, $stored);
                $this->log($request, $photo, 'site_photo.uploaded', ['caption' => $photo->caption, 'photo_date' => $photo->photo_date?->format('Y-m-d'), 'time_category' => $photo->time_category]);

                return $photo;
            });
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                Storage::disk(self::DISK)->delete($path);
            }
            throw $e;
        }

        return response()->json(['data' => new SitePhotoResource($this->load($photo)), 'message' => 'Site photo uploaded successfully.'], 201);
    }

    public function batch(BatchStoreSitePhotoRequest $request, TenantContext $tenant, int $id): JsonResponse
    {
        $project = $this->project($request, $tenant, $id);
        if (! $project) {
            return $this->notFound();
        }
        if (! $request->user()->can('photos.create') || ! $request->user()->can('create', [SitePhoto::class, $project])) {
            return $this->forbidden();
        }
        $stored = [];
        try {
            $photos = DB::transaction(function () use ($request, $tenant, $project, &$stored) {
                $metadata = Arr::except($request->validated(), 'photos');
                $photos = collect($request->file('photos'))->map(fn (UploadedFile $file) => $this->persist($file, $metadata, $tenant->companyId(), $project->id, $request->user()->id, $stored));
                activity('site_photos')->causedBy($request->user())->event('site_photo.batch_uploaded')->withProperties([
                    'company_id' => $tenant->companyId(), 'project_id' => $project->id, 'photo_ids' => $photos->pluck('id')->all(), 'count' => $photos->count(),
                ])->log('site_photo.batch_uploaded');

                return $photos;
            });
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                Storage::disk(self::DISK)->delete($path);
            }
            throw $e;
        }
        $photos->each->load('uploader:id,name');

        return response()->json(['data' => SitePhotoResource::collection($photos), 'message' => 'Site photos uploaded successfully.'], 201);
    }

    public function show(Request $request, TenantContext $tenant, int $id, int $photoId): JsonResponse
    {
        if (! $request->user()->can('photos.view')) {
            return $this->forbidden();
        }
        $photo = $this->photo($request, $tenant, $id, $photoId);

        return $photo ? response()->json(['data' => new SitePhotoResource($this->load($photo))]) : $this->notFound();
    }

    public function file(Request $request, TenantContext $tenant, int $id, int $photoId)
    {
        if (! $request->user()->can('photos.view')) {
            return $this->notFound();
        }
        $photo = $this->photo($request, $tenant, $id, $photoId);
        if (! $photo || $photo->storage_disk !== self::DISK || ! Storage::disk(self::DISK)->exists($photo->storage_path)) {
            return $this->notFound();
        }
        $extension = match ($photo->mime_type) {
            'image/jpeg' => 'jpg','image/png' => 'png','image/webp' => 'webp',default => 'img'
        };

        return response()->stream(function () use ($photo): void {
            $stream = Storage::disk(self::DISK)->readStream($photo->storage_path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, ['Content-Type' => $photo->mime_type, 'Content-Length' => (string) $photo->file_size,
            'Content-Disposition' => 'inline; filename="site-photo-'.$photo->id.'.'.$extension.'"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function update(UpdateSitePhotoRequest $request, TenantContext $tenant, int $id, int $photoId): JsonResponse
    {
        $photo = $this->photo($request, $tenant, $id, $photoId, false);
        if (! $photo) {
            return $this->notFound();
        }
        if (! $request->user()->can('photos.update') || ! $request->user()->can('update', $photo)) {
            return $this->forbidden();
        }
        $before = $photo->only(array_keys($request->validated()));
        $photo->update($request->validated());
        $this->log($request, $photo, 'site_photo.updated', ['before' => $before, 'after' => $photo->only(array_keys($request->validated()))]);

        return response()->json(['data' => new SitePhotoResource($this->load($photo)), 'message' => 'Site photo updated successfully.']);
    }

    public function review(ReviewSitePhotoRequest $request, TenantContext $tenant, int $id, int $photoId): JsonResponse
    {
        $photo = $this->photo($request, $tenant, $id, $photoId, false);
        if (! $photo) {
            return $this->notFound();
        }
        if (! $request->user()->can('photos.approve') || ! $request->user()->can('review', $photo)) {
            return $this->forbidden();
        }
        if ($photo->approval_status !== 'pending') {
            return $this->transitionError();
        }
        $v = $request->validated();
        $photo->approval_status = $v['action'] === 'approve' ? 'approved' : 'rejected';
        $photo->review_notes = $v['review_notes'] ?? null;
        $photo->reviewed_by = $request->user()->id;
        $photo->reviewed_at = now();
        $photo->is_client_visible = false;
        $photo->save();
        $event = $v['action'] === 'approve' ? 'site_photo.approved' : 'site_photo.rejected';
        $this->log($request, $photo, $event, ['action' => $v['action'], 'review_notes' => $photo->review_notes]);

        return response()->json(['data' => new SitePhotoResource($this->load($photo)), 'message' => 'Site photo reviewed successfully.']);
    }

    public function visibility(UpdateSitePhotoVisibilityRequest $request, TenantContext $tenant, int $id, int $photoId): JsonResponse
    {
        $photo = $this->photo($request, $tenant, $id, $photoId, false);
        if (! $photo) {
            return $this->notFound();
        }
        if (! $request->user()->can('photos.approve') || ! $request->user()->can('visibility', $photo)) {
            return $this->forbidden();
        }
        $visible = $request->validated('is_client_visible');
        if ($visible && $photo->approval_status !== 'approved') {
            return response()->json(['message' => 'Only approved photos can be made client-visible.', 'errors' => ['is_client_visible' => ['Only approved photos can be made client-visible.']]], 422);
        }
        $before = $photo->is_client_visible;
        $photo->is_client_visible = $visible;
        $photo->save();
        $this->log($request, $photo, 'site_photo.visibility_changed', ['before' => $before, 'after' => $visible]);

        return response()->json(['data' => new SitePhotoResource($this->load($photo)), 'message' => 'Client visibility updated successfully.']);
    }

    public function destroy(Request $request, TenantContext $tenant, int $id, int $photoId): JsonResponse
    {
        $photo = $this->photo($request, $tenant, $id, $photoId, false);
        if (! $photo) {
            return $this->notFound();
        }
        if (! $request->user()->can('photos.delete') || ! $request->user()->can('delete', $photo)) {
            return $this->forbidden();
        }
        $this->log($request, $photo, 'site_photo.deleted', ['file_retained' => true]);
        $photo->delete();

        return response()->json(['message' => 'Site photo deleted successfully.']);
    }

    public function reportPhotos(Request $request, TenantContext $tenant, int $id, int $reportId): JsonResponse
    {
        $project = $this->project($request, $tenant, $id);
        if (! $project) {
            return $this->notFound();
        }
        $report = DailySiteReport::query()->forCompany($tenant->companyId())->where('project_id', $project->id)->find($reportId);
        if (! $report || ! $request->user()->can('daily_reports.view') || ! $request->user()->can('view', $report)) {
            return response()->json(['message' => 'Daily site report not found.'], 404);
        }
        if (! $request->user()->can('photos.view')) {
            return $this->forbidden();
        }
        $query = $report->sitePhotos()->with('uploader:id,name');
        if ($request->user()->hasRole('client')) {
            $query->where('approval_status', 'approved')->where('is_client_visible', true);
        }

        return response()->json(['data' => SitePhotoResource::collection($query->orderByDesc('photo_date')->get())]);
    }

    private function persist(UploadedFile $file, array $metadata, int $companyId, int $projectId, int $userId, array &$stored): SitePhoto
    {
        $mime = $file->getMimeType();
        $extension = match ($mime) {
            'image/jpeg' => 'jpg','image/png' => 'png','image/webp' => 'webp',default => throw new \InvalidArgumentException('Unsupported image type.')
        };
        $name = Str::uuid().'.'.$extension;
        $path = "companies/{$companyId}/projects/{$projectId}/site-photos/{$name}";
        if (! Storage::disk(self::DISK)->putFileAs(dirname($path), $file, $name)) {
            throw new \RuntimeException('The photo could not be stored.');
        }
        $stored[] = $path;
        $photo = new SitePhoto($metadata);
        $photo->forceFill(['company_id' => $companyId, 'project_id' => $projectId, 'uploaded_by' => $userId,
            'original_filename' => basename($file->getClientOriginalName()), 'stored_filename' => $name, 'storage_disk' => self::DISK, 'storage_path' => $path,
            'mime_type' => $mime, 'file_size' => $file->getSize(), 'approval_status' => 'pending', 'is_client_visible' => false]);
        $photo->save();

        return $photo;
    }

    private function project(Request $request, TenantContext $tenant, int $id): ?Project
    {
        $project = Project::query()->forCompany($tenant->companyId())->find($id);

        return $project && $request->user()->can('view', $project) ? $project : null;
    }

    private function photo(Request $request, TenantContext $tenant, int $projectId, int $photoId, bool $enforceView = true): ?SitePhoto
    {
        if (! $this->project($request, $tenant, $projectId)) {
            return null;
        }
        $photo = SitePhoto::query()->forCompany($tenant->companyId())->where('project_id', $projectId)->find($photoId);

        return $photo && (! $enforceView || $request->user()->can('view', $photo)) ? $photo : null;
    }

    private function load(SitePhoto $photo): SitePhoto
    {
        return $photo->load('uploader:id,name');
    }

    private function log(Request $request, SitePhoto $photo, string $event, array $changes): void
    {
        activity('site_photos')->causedBy($request->user())->performedOn($photo)->event($event)->withProperties([
            'company_id' => $photo->company_id, 'project_id' => $photo->project_id, 'photo_id' => $photo->id, 'changes' => $changes,
        ])->log($event);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Site photo not found.'], 404);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
    }

    private function transitionError(): JsonResponse
    {
        return response()->json(['message' => 'The requested photo workflow transition is not allowed.', 'errors' => ['approval_status' => ['Only pending photos can be reviewed.']]], 422);
    }
}
