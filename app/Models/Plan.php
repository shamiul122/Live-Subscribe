<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Support\Billing\Money;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $price_minor
 * @property string $currency
 * @property BillingInterval $billing_interval
 * @property int $billing_interval_count
 * @property int $trial_days
 * @property bool $is_active
 * @property Carbon|null $archived_at
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'description',
    'price_minor',
    'currency',
    'billing_interval',
    'billing_interval_count',
    'trial_days',
    'is_active',
    'sort_order',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * The slug is deliberately not mass assignable. It is set once by the
     * plan action/seeder and never changes afterwards.
     */
    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'billing_interval' => BillingInterval::class,
            'billing_interval_count' => 'integer',
            'trial_days' => 'integer',
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<PlanFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @param  Builder<Plan>  $query
     */
    public function scopePurchasable(Builder $query): void
    {
        $query->where('is_active', true)->whereNull('archived_at');
    }

    public function isPurchasable(): bool
    {
        return $this->is_active && $this->archived_at === null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function hasTrial(): bool
    {
        return $this->trial_days > 0;
    }

    public function priceFormatted(): string
    {
        return Money::format($this->price_minor, $this->currency);
    }

    public function intervalLabel(): string
    {
        if ($this->billing_interval_count === 1) {
            return $this->billing_interval->label();
        }

        return $this->billing_interval_count.' × '.$this->billing_interval->label();
    }

    /**
     * Resolve route model binding by slug instead of the primary key.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
