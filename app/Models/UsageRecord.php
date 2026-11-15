<?php

namespace App\Models;

use App\Enums\Feature;
use Database\Factories\UsageRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $subscription_id
 * @property Feature $feature
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property int $used
 * @property int|null $limit_snapshot
 */
#[Fillable([
    'organization_id',
    'subscription_id',
    'feature',
    'period_start',
    'period_end',
    'used',
    'limit_snapshot',
])]
class UsageRecord extends Model
{
    /** @use HasFactory<UsageRecordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'feature' => Feature::class,
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'used' => 'integer',
            'limit_snapshot' => 'integer',
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
