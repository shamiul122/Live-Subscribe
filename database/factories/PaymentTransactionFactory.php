<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'mock',
            'provider_reference' => null,
            'status' => PaymentStatus::Pending,
            'amount_minor' => 1900,
            'currency' => 'USD',
            'failure_code' => null,
            'failure_message' => null,
            'payload' => null,
            'processed_at' => null,
        ];
    }

    /**
     * order_id and organization_id must belong together to satisfy the
     * composite tenant foreign key, so they are resolved explicitly.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): PaymentTransaction
    {
        $orderId = $attributes['order_id'] ?? null;
        $organizationId = $attributes['organization_id'] ?? null;

        unset($attributes['order_id'], $attributes['organization_id']);

        if ($orderId === null) {
            $order = Order::factory()->create();
            $orderId = $order->id;
            $organizationId ??= $order->organization_id;
        } elseif ($organizationId === null) {
            $organizationId = Order::query()->whereKey($orderId)->value('organization_id');
        }

        $transaction = parent::newModel($attributes);
        $transaction->order_id = (int) $orderId;
        $transaction->organization_id = (int) $organizationId;

        return $transaction;
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (): array => [
            'order_id' => $order->id,
            'organization_id' => $order->organization_id,
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Succeeded,
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Failed,
            'failure_code' => 'mock_declined',
            'failure_message' => 'The mock payment was declined.',
            'processed_at' => now(),
        ]);
    }
}
