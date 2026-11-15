<?php

namespace App\Actions\Billing;

use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * Expire pending orders that were never paid within their lifetime.
 */
class ExpirePendingOrders
{
    public function handle(?int $expiryDays = null): int
    {
        $expiryDays ??= (int) config('billing.order_expiry_days', 7);

        return Order::query()
            ->where('status', OrderStatus::Pending->value)
            ->where('created_at', '<=', now()->subDays($expiryDays))
            ->update([
                'status' => OrderStatus::Expired->value,
                'updated_at' => now(),
            ]);
    }
}
