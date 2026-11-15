<?php

namespace App\Models;

use App\Enums\DeliveryAttemptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A delivery attempt for an outbound message.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $conversation_id
 * @property int $message_id
 * @property int $connected_account_id
 * @property string $provider
 * @property int $attempt_number
 * @property DeliveryAttemptStatus $status
 * @property string|null $provider_message_id
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $attempted_at
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'conversation_id',
    'message_id',
    'connected_account_id',
    'provider',
    'attempt_number',
    'status',
    'provider_message_id',
    'error_code',
    'error_message',
    'attempted_at',
    'completed_at',
    'metadata',
])]
class DeliveryAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'conversation_id' => 'integer',
            'message_id' => 'integer',
            'connected_account_id' => 'integer',
            'provider' => 'string',
            'attempt_number' => 'integer',
            'status' => DeliveryAttemptStatus::class,
            'provider_message_id' => 'string',
            'error_code' => 'string',
            'error_message' => 'string',
            'attempted_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<ConnectedAccount, $this>
     */
    public function connectedAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectedAccount::class);
    }
}
