<?php

namespace App\Models;

use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only audit trail. There is no updated_at.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $subscription_id
 * @property SubscriptionEventType $type
 * @property SubscriptionStatus|null $from_status
 * @property SubscriptionStatus|null $to_status
 * @property int|null $actor_id
 * @property array<string, mixed>|null $data
 * @property Carbon|null $created_at
 */
#[Fillable([
    'organization_id',
    'subscription_id',
    'type',
    'from_status',
    'to_status',
    'actor_id',
    'data',
    'created_at',
])]
class SubscriptionEvent extends Model
{
    /** @use HasFactory<SubscriptionEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'type' => SubscriptionEventType::class,
            'from_status' => SubscriptionStatus::class,
            'to_status' => SubscriptionStatus::class,
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
