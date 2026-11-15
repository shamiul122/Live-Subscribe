<?php

namespace App\Models;

use App\Enums\Feature;
use Database\Factories\UsageEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Durable idempotency ledger for metered usage. Append-only.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $subscription_id
 * @property Feature $feature
 * @property Carbon $period_start
 * @property string $idempotency_key
 * @property int $units
 * @property Carbon|null $created_at
 */
#[Fillable([
    'organization_id',
    'subscription_id',
    'feature',
    'period_start',
    'idempotency_key',
    'units',
    'created_at',
])]
class UsageEvent extends Model
{
    /** @use HasFactory<UsageEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'feature' => Feature::class,
            'period_start' => 'datetime',
            'units' => 'integer',
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
}
