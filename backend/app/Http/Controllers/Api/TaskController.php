<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListTasksRequest;
use App\Http\Requests\Api\V1\StoreTaskRequest;
use App\Http\Requests\Api\V1\TaskRequest;
use App\Http\Requests\Api\V1\UpdateTaskRequest;
use App\Http\Requests\Api\V1\UpdateTaskStatusRequest;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function index(ListTasksRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = Task::query()->accessibleTo($request->user());

        if ($request->project !== null) {
            $query->where('project_id', $request->project->id);
        }

        foreach (Arr::only($filters, ['project_id', 'phase_id', 'milestone_id', 'assigned_to', 'status', 'priority']) as $field => $value) {
            $query->where($field, $value);
        }

        if (isset($filters['date_from'])) {
            $query->where('due_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->where('due_date', '<=', $filters['date_to']);
        }
        if (isset($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn (Builder $tasks) => $tasks->whereLike('title', $search)->orWhereLike('description', $search));
        }

        $page = $query->orderBy($filters['sort'] ?? 'position', $filters['direction'] ?? 'asc')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return TaskResource::collection($page);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->tasks->create($request->project, $request->user(), $request->validated());

        return (new TaskResource($task))->response()->setStatusCode(201);
    }

    public function show(TaskRequest $request): TaskResource
    {
        return new TaskResource($request->task);
    }

    public function update(UpdateTaskRequest $request): TaskResource
    {
        return new TaskResource($this->tasks->update($request->project, $request->task, $request->validated(), $request->user()));
    }

    public function status(UpdateTaskStatusRequest $request): TaskResource
    {
        return new TaskResource($this->tasks->update($request->project, $request->task, $request->validated(), $request->user()));
    }

    public function destroy(TaskRequest $request): Response
    {
        $this->tasks->delete($request->project, $request->task, $request->user());

        return response()->noContent();
    }
}
