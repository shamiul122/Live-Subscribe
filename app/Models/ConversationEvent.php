<?php

namespace App\Models;

use Database\Factories\ConversationEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only lifecycle history for inbox conversations. Never stores tokens
 * or credentials; only event types, actors and metadata.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $conversation_id
 * @property string $event_type
 * @property int|null $actor_id
 * @property array<string, mixed>|null $event_data
 * @property Carbon|null $created_at
 */
#[Fillable([
    'organization_id',
    'conversation_id',
    'event_type',
    'actor_id',
    'event_data',
    'created_at',
])]
class ConversationEvent extends Model
{
    /** @use HasFactory<ConversationEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'conversation_id' => 'integer',
            'event_type' => 'string',
            'actor_id' => 'integer',
            'event_data' => 'array',
            'created_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
