<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\Feature;
use App\Enums\SubscriptionStatus;
use App\Support\Billing\Money;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon $current_period_start
 * @property Carbon $current_period_end
 * @property Carbon|null $grace_ends_at
 * @property bool $cancel_at_period_end
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $suspended_at
 * @property Carbon|null $ended_at
 * @property int $price_minor
 * @property string $currency
 * @property BillingInterval $billing_interval
 * @property int $billing_interval_count
 * @property int|null $pending_plan_id
 * @property string $provider
 * @property string|null $provider_subscription_id
 * @property Carbon|null $last_renewed_at
 */
#[Fillable([
    'organization_id',
    'plan_id',
    'status',
    'trial_ends_at',
    'current_period_start',
    'current_period_end',
    'grace_ends_at',
    'cancel_at_period_end',
    'cancelled_at',
    'suspended_at',
    'ended_at',
    'price_minor',
    'currency',
    'billing_interval',
    'billing_interval_count',
    'pending_plan_id',
    'provider',
    'provider_subscription_id',
    'last_renewed_at',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'cancelled_at' => 'datetime',
            'suspended_at' => 'datetime',
            'ended_at' => 'datetime',
            'price_minor' => 'integer',
            'billing_interval' => BillingInterval::class,
            'billing_interval_count' => 'integer',
            'last_renewed_at' => 'datetime',
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
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    /**
     * @return HasMany<SubscriptionEntitlement, $this>
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(SubscriptionEntitlement::class);
    }

    /**
     * @return HasMany<SubscriptionEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    /**
     * @return HasMany<License, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<UsageRecord, $this>
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function isNonTerminal(): bool
    {
        return $this->status->isNonTerminal();
    }

    public function isTrialing(): bool
    {
        return $this->status === SubscriptionStatus::Trialing;
    }

    /**
     * The moment entitlement ends for the current status.
     */
    public function entitledUntil(): ?Carbon
    {
        return match ($this->status) {
            SubscriptionStatus::Trialing => $this->trial_ends_at,
            SubscriptionStatus::PastDue => $this->grace_ends_at,
            SubscriptionStatus::Active, SubscriptionStatus::Cancelled => $this->current_period_end,
            SubscriptionStatus::Suspended, SubscriptionStatus::Expired => null,
        };
    }

    public function isEntitledAt(Carbon $at): bool
    {
        return match ($this->status) {
            SubscriptionStatus::Trialing => $this->trial_ends_at !== null && $at->lessThan($this->trial_ends_at),
            SubscriptionStatus::Active => $at->lessThan($this->current_period_end),
            SubscriptionStatus::PastDue => $this->grace_ends_at !== null && $at->lessThanOrEqualTo($this->grace_ends_at),
            SubscriptionStatus::Cancelled => $at->lessThan($this->current_period_end),
            SubscriptionStatus::Suspended, SubscriptionStatus::Expired => false,
        };
    }

    public function entitlementLimit(Feature $feature): ?int
    {
        /** @var SubscriptionEntitlement|null $entitlement */
        $entitlement = $this->entitlements()->where('feature', $feature->value)->first();

        return $entitlement?->limit;
    }

    public function hasFeature(Feature $feature): bool
    {
        return $this->entitlements()->where('feature', $feature->value)->exists();
    }

    public function priceFormatted(): string
    {
        return Money::format($this->price_minor, $this->currency);
    }
}
