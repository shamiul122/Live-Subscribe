<?php

namespace App\Models;

use App\Enums\Feature;
use Database\Factories\SubscriptionEntitlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $subscription_id
 * @property Feature $feature
 * @property int|null $limit
 */
#[Fillable(['organization_id', 'subscription_id', 'feature', 'limit'])]
class SubscriptionEntitlement extends Model
{
    /** @use HasFactory<SubscriptionEntitlementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'feature' => Feature::class,
            'limit' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isUnlimited(): bool
    {
        return $this->limit === null;
    }
}
