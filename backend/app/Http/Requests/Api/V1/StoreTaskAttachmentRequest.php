<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Attachment;
use Closure;
use Illuminate\Http\UploadedFile;

class StoreTaskAttachmentRequest extends TaskAttachmentRequest
{
    private const MIME_TYPES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-word'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel', 'application/msexcel', 'application/x-msexcel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ];

    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'nullable', 'string', 'max:50'],
            'file' => ['bail', 'required', 'file', 'max:10240', $this->validateFile(...)],
        ] + parent::rules();
    }

    public function messages(): array
    {
        return [
            'file.uploaded' => 'The file could not be uploaded. The maximum attachment size is 10 MB.',
            'file.max' => 'The file must be no larger than 10 MB.',
        ];
    }

    private function validateFile(string $attribute, UploadedFile $file, Closure $fail): void
    {
        $name = $file->getClientOriginalName();
        if (preg_match('~^[^/\\\\%\p{C}]{1,255}$~uD', $name) !== 1 || trim($name) !== $name) {
            $fail('The attachment filename is invalid.');

            return;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        if (! in_array($mime, self::MIME_TYPES[$extension] ?? [], true)) {
            $fail('The file must be a JPEG, PNG, WebP, PDF, DOC, DOCX, XLS, or XLSX with a matching extension.');

            return;
        }

        if (in_array($mime, Attachment::IMAGE_TYPES, true)
            && (@getimagesize($file->getPathname())['mime'] ?? null) !== $mime) {
            $fail('The file must contain a valid supported image.');
        }
    }
}
