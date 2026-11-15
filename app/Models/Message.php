<?php

namespace App\Models;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single message inside an organization-scoped conversation.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $conversation_id
 * @property string|null $provider_message_id
 * @property MessageDirection $direction
 * @property MessageType $message_type
 * @property MessageDeliveryStatus|null $delivery_status
 * @property string|null $idempotency_key
 * @property string|null $body
 * @property string|null $sender_type
 * @property int|null $sender_id
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $failed_at
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'conversation_id',
    'provider_message_id',
    'direction',
    'message_type',
    'delivery_status',
    'idempotency_key',
    'body',
    'sender_type',
    'sender_id',
    'sent_at',
    'delivered_at',
    'read_at',
    'failed_at',
    'failure_reason',
    'metadata',
])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'conversation_id' => 'integer',
            'provider_message_id' => 'string',
            'direction' => MessageDirection::class,
            'message_type' => MessageType::class,
            'delivery_status' => MessageDeliveryStatus::class,
            'sender_type' => 'string',
            'sender_id' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * The internal actor that produced the message. Provider-side senders are
     * tracked through sender_type/sender_id on inbound records.
     *
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return HasMany<ConversationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ConversationEvent::class);
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * @return HasMany<DeliveryAttempt, $this>
     */
    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class);
    }

    public function isOutbound(): bool
    {
        return $this->direction === MessageDirection::Outbound;
    }

    public function isRetryable(): bool
    {
        return $this->isOutbound() && $this->delivery_status === MessageDeliveryStatus::Failed;
    }
}
