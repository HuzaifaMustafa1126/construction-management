<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListTaskCommentsRequest;
use App\Http\Requests\Api\V1\SaveTaskCommentRequest;
use App\Http\Requests\Api\V1\TaskCommentRequest;
use App\Http\Resources\Api\V1\TaskCommentResource;
use App\Services\TaskCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaskCommentController extends Controller
{
    public function __construct(private readonly TaskCommentService $comments) {}

    public function index(ListTaskCommentsRequest $request): AnonymousResourceCollection
    {
        return TaskCommentResource::collection(
            $this->comments->paginate($request->task, (int) $request->validated('per_page', 15)),
        );
    }

    public function store(SaveTaskCommentRequest $request): JsonResponse
    {
        $comment = $this->comments->create($request->task, $request->user(), $request->validated('comment'));

        return (new TaskCommentResource($comment))->response()->setStatusCode(201);
    }

    public function update(SaveTaskCommentRequest $request): TaskCommentResource
    {
        return new TaskCommentResource(
            $this->comments->update($request->task, $request->comment, $request->validated('comment')),
        );
    }

    public function destroy(TaskCommentRequest $request): Response
    {
        $this->comments->delete($request->task, $request->comment);

        return response()->noContent();
    }
}
