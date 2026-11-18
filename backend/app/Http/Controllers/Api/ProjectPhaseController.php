<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProjectPhaseRequest;
use App\Http\Requests\Api\V1\ReorderProjectPhasesRequest;
use App\Http\Requests\Api\V1\StoreProjectPhaseRequest;
use App\Http\Requests\Api\V1\UpdateProjectPhaseRequest;
use App\Http\Resources\Api\V1\ProjectPhaseResource;
use App\Services\ProjectPhaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectPhaseController extends Controller
{
    public function __construct(private readonly ProjectPhaseService $phases) {}

    public function index(ProjectPhaseRequest $request): AnonymousResourceCollection
    {
        return ProjectPhaseResource::collection($request->project->phases()->get());
    }

    public function store(StoreProjectPhaseRequest $request): JsonResponse
    {
        $phase = $this->phases->create($request->project, $request->validated());

        return (new ProjectPhaseResource($phase))->response()->setStatusCode(201);
    }

    public function update(UpdateProjectPhaseRequest $request): ProjectPhaseResource
    {
        return new ProjectPhaseResource($this->phases->update($request->project, $request->phase, $request->validated()));
    }

    public function destroy(ProjectPhaseRequest $request): Response
    {
        $this->phases->delete($request->project, $request->phase);

        return response()->noContent();
    }

    public function reorder(ReorderProjectPhasesRequest $request): AnonymousResourceCollection
    {
        return ProjectPhaseResource::collection($this->phases->reorder($request->project, $request->validated('phase_ids')));
    }

    public function applyDefaults(ProjectPhaseRequest $request): JsonResponse
    {
        return ProjectPhaseResource::collection($this->phases->applyDefaults($request->project))->response()->setStatusCode(201);
    }
}
