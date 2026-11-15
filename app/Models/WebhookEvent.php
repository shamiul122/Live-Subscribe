<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only record of inbound webhook processing.
 *
 * @property int $id
 * @property string $provider
 * @property int|null $connected_account_id
 * @property string|null $provider_event_id
 * @property string $event_type
 * @property string $processing_status
 * @property int $attempts
 * @property Carbon|null $received_at
 * @property Carbon|null $processed_at
 * @property string|null $failure_reason
 * @property string $payload_hash
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'provider',
    'connected_account_id',
    'provider_event_id',
    'event_type',
    'processing_status',
    'attempts',
    'received_at',
    'processed_at',
    'failure_reason',
    'payload_hash',
    'payload',
])]
class WebhookEvent extends Model
{
    protected function casts(): array
    {
        return [
            'provider' => 'string',
            'connected_account_id' => 'integer',
            'provider_event_id' => 'string',
            'event_type' => 'string',
            'processing_status' => 'string',
            'attempts' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'payload' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<ConnectedAccount, $this>
     */
    public function connectedAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectedAccount::class);
    }
}
