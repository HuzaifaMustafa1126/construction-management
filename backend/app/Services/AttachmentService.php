<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AttachmentService
{
    private const DISK = 'local';

    public function paginate(MorphMany $attachments, int $perPage): LengthAwarePaginator
    {
        return $attachments->latest('id')->paginate($perPage);
    }

    public function find(MorphMany $attachments, string $id, bool $includePendingDeletion): Attachment
    {
        if ($includePendingDeletion) {
            $attachments->withTrashed();
        }

        return $attachments->findOrFail($id);
    }

    public function upload(MorphMany $attachments, User $user, UploadedFile $file, ?string $category, string $directory): Attachment
    {
        $name = Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
        $path = $directory.'/'.$name;
        $disk = Storage::disk(self::DISK);

        try {
            if ($disk->putFileAs($directory, $file, $name, 'private') === false) {
                throw new RuntimeException('Could not write attachment storage object.');
            }

            $attachment = new Attachment(['category' => $category]);
            $attachment->forceFill([
                'uploaded_by' => $user->id,
                'disk' => self::DISK,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);

            if ($attachments->save($attachment) === false) {
                throw new RuntimeException('Could not save attachment metadata.');
            }

            return $attachment;
        } catch (Throwable $exception) {
            report($exception);

            try {
                if (! $disk->delete($path)) {
                    report(new RuntimeException('Could not clean up failed attachment upload: '.$path));
                }
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }

            abort(503, 'The attachment could not be saved.');
        }
    }

    public function stream(Attachment $attachment, bool $inline): StreamedResponse
    {
        abort_if($inline && ! in_array($attachment->mime_type, Attachment::IMAGE_TYPES, true), 415, 'Only images can be previewed.');

        $disposition = HeaderUtils::makeDisposition(
            $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
            $attachment->file_name,
            'attachment-'.basename($attachment->file_path),
        );

        try {
            $stream = Storage::disk($attachment->disk)->readStream($attachment->file_path);
        } catch (Throwable $exception) {
            report($exception);
            abort(503, 'The attachment could not be read.');
        }
        abort_unless(is_resource($stream), 404, 'The attachment file is unavailable.');

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function delete(Attachment $attachment): void
    {
        // Commit the tombstone before touching storage. DELETE can resume a partial
        // cleanup, while all reads exclude tombstones.
        try {
            if (! $attachment->trashed() && ! $attachment->delete()) {
                throw new RuntimeException('Could not mark attachment for deletion.');
            }

            $disk = Storage::disk($attachment->disk);
            if ($disk->exists($attachment->file_path) && ! $disk->delete($attachment->file_path)) {
                throw new RuntimeException('Could not remove attachment storage object.');
            }

            if (! $attachment->forceDelete()) {
                throw new RuntimeException('Could not remove attachment metadata.');
            }
        } catch (Throwable $exception) {
            report($exception);
            abort(503, 'Attachment deletion could not finish. Retry the delete request.');
        }
    }
}
