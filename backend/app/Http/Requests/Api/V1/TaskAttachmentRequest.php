<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Attachment;
use App\Models\Task;
use App\Services\AttachmentService;
use Illuminate\Support\Facades\Gate;

class TaskAttachmentRequest extends TaskRequest
{
    public ?Attachment $attachment = null;

    public function authorize(): bool
    {
        parent::resolveResources();

        if ($this->route('attachmentId') !== null) {
            $this->attachment = app(AttachmentService::class)->find(
                $this->task->attachments(),
                $this->route('attachmentId'),
                $this->isMethod('DELETE'),
            );
        }

        if (! $this->allows('viewAny')) {
            return false;
        }

        return match ($this->method()) {
            'POST' => $this->allows('update'),
            'DELETE' => Gate::allows('deleteAttachment', [Task::class, $this->project, $this->attachment]),
            default => true,
        };
    }

    public function rules(): array
    {
        return array_fill_keys([
            'uploaded_by', 'disk', 'file_path', 'thumbnail_path', 'file_name',
            'mime_type', 'file_size', 'attachable_type', 'attachable_id', 'task_id',
        ], ['missing']) + parent::rules();
    }
}
