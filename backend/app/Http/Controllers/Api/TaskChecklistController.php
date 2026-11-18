<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReorderTaskChecklistRequest;
use App\Http\Requests\Api\V1\StoreTaskChecklistItemRequest;
use App\Http\Requests\Api\V1\TaskChecklistRequest;
use App\Http\Requests\Api\V1\UpdateTaskChecklistItemRequest;
use App\Http\Resources\Api\V1\TaskChecklistItemResource;
use App\Services\TaskChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaskChecklistController extends Controller
{
    public function __construct(private readonly TaskChecklistService $checklist) {}

    public function index(TaskChecklistRequest $request): AnonymousResourceCollection
    {
        return TaskChecklistItemResource::collection($request->task->checklistItems()->get());
    }

    public function store(StoreTaskChecklistItemRequest $request): JsonResponse
    {
        $item = $this->checklist->create($request->task, $request->validated());

        return (new TaskChecklistItemResource($item))->response()->setStatusCode(201);
    }

    public function update(UpdateTaskChecklistItemRequest $request): TaskChecklistItemResource
    {
        return new TaskChecklistItemResource($this->checklist->update($request->task, $request->item, $request->validated()));
    }

    public function destroy(TaskChecklistRequest $request): Response
    {
        $this->checklist->delete($request->task, $request->item);

        return response()->noContent();
    }

    public function reorder(ReorderTaskChecklistRequest $request): AnonymousResourceCollection
    {
        return TaskChecklistItemResource::collection(
            $this->checklist->reorder($request->task, $request->validated('item_ids')),
        );
    }
}
