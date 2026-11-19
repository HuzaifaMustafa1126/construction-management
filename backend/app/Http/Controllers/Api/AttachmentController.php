<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListTaskAttachmentsRequest;
use App\Http\Requests\Api\V1\StoreTaskAttachmentRequest;
use App\Http\Requests\Api\V1\TaskAttachmentRequest;
use App\Http\Resources\Api\V1\AttachmentResource;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $attachments) {}

    public function index(ListTaskAttachmentsRequest $request): AnonymousResourceCollection
    {
        return AttachmentResource::collection(
            $this->attachments->paginate($request->task->attachments(), (int) $request->validated('per_page', 15)),
        );
    }

    public function store(StoreTaskAttachmentRequest $request): JsonResponse
    {
        $task = $request->task;
        $directory = "task-attachments/companies/{$task->company_id}/projects/{$task->project_id}/tasks/{$task->id}/attachments";
        $attachment = $this->attachments->upload(
            $task->attachments(),
            $request->user(),
            $request->validated('file'),
            $request->validated('category'),
            $directory,
        );

        return (new AttachmentResource($attachment))->response()->setStatusCode(201);
    }

    public function show(TaskAttachmentRequest $request): AttachmentResource
    {
        return new AttachmentResource($request->attachment);
    }

    public function preview(TaskAttachmentRequest $request): StreamedResponse
    {
        return $this->attachments->stream($request->attachment, inline: true);
    }

    public function download(TaskAttachmentRequest $request): StreamedResponse
    {
        return $this->attachments->stream($request->attachment, inline: false);
    }

    public function destroy(TaskAttachmentRequest $request): Response
    {
        $this->attachments->delete($request->attachment);

        return response()->noContent();
    }
}
