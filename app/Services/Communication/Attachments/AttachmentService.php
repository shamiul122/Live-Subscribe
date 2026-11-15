<?php

namespace App\Services\Communication\Attachments;

use App\Models\Attachment;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores and validates inbox attachments.
 *
 * Files are written to the configured disk under an organization-scoped
 * directory with a random filename. The original name is retained only for
 * display and download.
 */
final class AttachmentService
{
    /**
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     * @return array<int, Attachment>
     */
    public function storeMany(Organization $organization, ?User $uploader, array $files, ?Message $message = null): array
    {
        $max = (int) config('communication.attachments.max_per_message', 5);

        if (count($files) > $max) {
            throw ValidationException::withMessages([
                'attachments' => "A message can include at most {$max} attachments.",
            ]);
        }

        $stored = [];

        foreach ($files as $file) {
            $stored[] = $this->store($organization, $uploader, $file, $message);
        }

        return $stored;
    }

    public function store(Organization $organization, ?User $uploader, UploadedFile $file, ?Message $message = null): Attachment
    {
        $this->assertAllowed($file);

        $disk = (string) config('communication.attachments.disk', 'local');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
        $path = $file->storeAs(
            'attachments/'.$organization->id,
            Str::uuid()->toString().'.'.$extension,
            $disk,
        );

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['attachments' => 'The attachment could not be stored.']);
        }

        $mimeType = $file->getMimeType() ?? 'application/octet-stream';

        return Attachment::query()->create([
            'organization_id' => $organization->id,
            'message_id' => $message?->id,
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'disk' => $disk,
            'uploaded_by_id' => $uploader?->id,
            'mime_type' => $mimeType,
            'size' => $file->getSize() ?: 0,
            'checksum' => hash_file('sha256', $file->getRealPath()) ?: hash('sha256', $path),
            'attachment_type' => str_starts_with($mimeType, 'image/') ? 'image' : 'file',
            'provider_media_id' => null,
            'metadata' => ['source' => 'upload'],
        ]);
    }

    /**
     * Attach previously uploaded, still-unattached files to a message.
     *
     * @param  array<int, int>  $attachmentIds
     * @return Collection<int, Attachment>
     */
    public function attachToMessage(Organization $organization, Message $message, array $attachmentIds): Collection
    {
        $attachmentIds = array_values(array_unique(array_map('intval', $attachmentIds)));

        if ($attachmentIds === []) {
            return new Collection();
        }

        $max = (int) config('communication.attachments.max_per_message', 5);

        if (count($attachmentIds) > $max) {
            throw ValidationException::withMessages([
                'attachments' => "A message can include at most {$max} attachments.",
            ]);
        }

        $attachments = Attachment::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $attachmentIds)
            ->get();

        if ($attachments->count() !== count($attachmentIds)) {
            throw ValidationException::withMessages(['attachments' => 'One or more attachments could not be found.']);
        }

        foreach ($attachments as $attachment) {
            if ($attachment->message_id !== null && $attachment->message_id !== $message->id) {
                throw ValidationException::withMessages([
                    'attachments' => 'An attachment is already linked to another message.',
                ]);
            }
        }

        Attachment::query()
            ->whereIn('id', $attachmentIds)
            ->update(['message_id' => $message->id]);

        return Attachment::query()->whereIn('id', $attachmentIds)->get();
    }

    /**
     * Remove an unlinked attachment and its stored file.
     */
    public function delete(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->storage_path);
        $attachment->delete();
    }

    /**
     * Roll back attachments that were stored before an outbound message could
     * be queued.
     *
     * @param  array<int, Attachment>  $attachments
     */
    public function deleteMany(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            $this->delete($attachment);
        }
    }

    /**
     * @return Collection<int, Attachment>
     */
    public function forMessage(Organization $organization, Message $message): Collection
    {
        if ($message->organization_id !== $organization->id) {
            throw new ModelNotFoundException("Message [{$message->id}] was not found for this organization.");
        }

        return Attachment::query()
            ->where('organization_id', $organization->id)
            ->where('message_id', $message->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'organization_id' => $attachment->organization_id,
            'message_id' => $attachment->message_id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'attachment_type' => $attachment->attachment_type,
            'is_image' => $attachment->isImage(),
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }

    private function assertAllowed(UploadedFile $file): void
    {
        $maxKb = (int) config('communication.attachments.max_size_kb', 10240);

        if ($file->getSize() !== false && $file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'attachments' => "Each attachment must be {$maxKb} KB or smaller.",
            ]);
        }

        $allowed = (array) config('communication.attachments.allowed_mimes', []);
        $mime = (string) ($file->getMimeType() ?? '');

        if ($allowed !== [] && ! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'attachments' => 'This attachment type is not allowed.',
            ]);
        }
    }
}
