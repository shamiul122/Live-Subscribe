<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Support\Billing\Money;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $subscription_id
 * @property int $plan_id
 * @property string $type
 * @property OrderStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property string $idempotency_key
 * @property int|null $placed_by_id
 * @property Carbon|null $paid_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'organization_id',
    'subscription_id',
    'plan_id',
    'type',
    'status',
    'amount_minor',
    'currency',
    'idempotency_key',
    'placed_by_id',
    'paid_at',
    'metadata',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'amount_minor' => 'integer',
            'paid_at' => 'datetime',
            'metadata' => 'array',
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
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by_id');
    }

    /**
     * @return HasMany<PaymentTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function isPayable(): bool
    {
        return $this->status->isPayable();
    }

    public function amountFormatted(): string
    {
        return Money::format($this->amount_minor, $this->currency);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'new' => 'New subscription',
            'renewal' => 'Renewal',
            'plan_change' => 'Plan change',
            default => ucfirst($this->type),
        };
    }
}
