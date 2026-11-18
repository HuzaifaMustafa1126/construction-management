<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListMilestonesRequest;
use App\Http\Requests\Api\V1\MilestoneRequest;
use App\Http\Requests\Api\V1\StoreMilestoneRequest;
use App\Http\Requests\Api\V1\UpdateMilestoneRequest;
use App\Http\Resources\Api\V1\MilestoneResource;
use App\Services\MilestoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MilestoneController extends Controller
{
    public function __construct(private readonly MilestoneService $milestones) {}

    public function index(ListMilestonesRequest $request): AnonymousResourceCollection
    {
        $query = $request->project->milestones();

        foreach ($request->validated() as $field => $value) {
            $query->where($field, $value);
        }

        return MilestoneResource::collection($query->get());
    }

    public function store(StoreMilestoneRequest $request): JsonResponse
    {
        $milestone = $this->milestones->create($request->project, $request->validated());

        return (new MilestoneResource($milestone))->response()->setStatusCode(201);
    }

    public function show(MilestoneRequest $request): MilestoneResource
    {
        return new MilestoneResource($request->milestone);
    }

    public function update(UpdateMilestoneRequest $request): MilestoneResource
    {
        return new MilestoneResource($this->milestones->update($request->project, $request->milestone, $request->validated()));
    }

    public function destroy(MilestoneRequest $request): Response
    {
        $this->milestones->delete($request->project, $request->milestone);

        return response()->noContent();
    }
}
