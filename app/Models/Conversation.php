<?php

namespace App\Models;

use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use App\Enums\SocialProvider;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An organization-scoped conversation inside the unified inbox.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $connected_account_id
 * @property int|null $contact_id
 * @property SocialProvider $provider
 * @property string|null $provider_conversation_id
 * @property string|null $subject
 * @property ConversationStatus $status
 * @property ConversationPriority $priority
 * @property int|null $assigned_to_id
 * @property Carbon|null $last_message_at
 * @property string|null $last_message_preview
 * @property int $unread_count
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'organization_id',
    'connected_account_id',
    'contact_id',
    'provider',
    'provider_conversation_id',
    'subject',
    'status',
    'priority',
    'assigned_to_id',
    'last_message_at',
    'last_message_preview',
    'unread_count',
    'metadata',
])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'connected_account_id' => 'integer',
            'contact_id' => 'integer',
            'provider' => SocialProvider::class,
            'provider_conversation_id' => 'string',
            'status' => ConversationStatus::class,
            'priority' => ConversationPriority::class,
            'assigned_to_id' => 'integer',
            'last_message_at' => 'datetime',
            'unread_count' => 'integer',
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
     * @return BelongsTo<ConnectedAccount, $this>
     */
    public function connectedAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectedAccount::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasMany<ConversationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ConversationEvent::class);
    }

    public function isOpen(): bool
    {
        return $this->status === ConversationStatus::Open;
    }
}
