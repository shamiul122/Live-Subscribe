<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An attachment associated with a message.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $message_id
 * @property string $original_name
 * @property string $storage_path
 * @property string $disk
 * @property int|null $uploaded_by_id
 * @property string $mime_type
 * @property int $size
 * @property string $checksum
 * @property string $attachment_type
 * @property string|null $provider_media_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'message_id',
    'original_name',
    'storage_path',
    'disk',
    'uploaded_by_id',
    'mime_type',
    'size',
    'checksum',
    'attachment_type',
    'provider_media_id',
    'metadata',
])]
class Attachment extends Model
{
    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'message_id' => 'integer',
            'original_name' => 'string',
            'storage_path' => 'string',
            'disk' => 'string',
            'uploaded_by_id' => 'integer',
            'mime_type' => 'string',
            'size' => 'integer',
            'checksum' => 'string',
            'attachment_type' => 'string',
            'provider_media_id' => 'string',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
