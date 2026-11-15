<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Support\Billing\Money;
use Database\Factories\PaymentTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $order_id
 * @property string $provider
 * @property string|null $provider_reference
 * @property PaymentStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $processed_at
 */
#[Fillable([
    'organization_id',
    'order_id',
    'provider',
    'provider_reference',
    'status',
    'amount_minor',
    'currency',
    'failure_code',
    'failure_message',
    'payload',
    'processed_at',
])]
class PaymentTransaction extends Model
{
    /** @use HasFactory<PaymentTransactionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_minor' => 'integer',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function amountFormatted(): string
    {
        return Money::format($this->amount_minor, $this->currency);
    }
}
