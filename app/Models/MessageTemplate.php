<?php

namespace App\Models;

use App\Enums\MessageTemplateCategory;
use App\Enums\MessageTemplateStatus;
use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An organization-scoped, reusable message template.
 *
 * A template only describes content. Rendering is delegated to
 * TemplateRendererService and no message is ever sent by this model.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $created_by_id
 * @property string $name
 * @property string|null $description
 * @property string|null $channel
 * @property MessageTemplateCategory $category
 * @property MessageTemplateStatus $status
 * @property string|null $subject
 * @property string $content
 * @property array<int, string>|null $variables
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'organization_id',
    'created_by_id',
    'name',
    'description',
    'channel',
    'category',
    'status',
    'subject',
    'content',
    'variables',
    'metadata',
])]
class MessageTemplate extends Model
{
    /** @use HasFactory<MessageTemplateFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'created_by_id' => 'integer',
            'category' => MessageTemplateCategory::class,
            'status' => MessageTemplateStatus::class,
            'variables' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isReusable(): bool
    {
        return $this->status->isReusable();
    }
}
