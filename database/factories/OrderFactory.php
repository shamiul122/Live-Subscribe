<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'subscription_id' => null,
            'plan_id' => Plan::factory(),
            'type' => 'new',
            'status' => OrderStatus::Pending,
            'amount_minor' => 1900,
            'currency' => 'USD',
            'idempotency_key' => (string) Str::uuid(),
            'placed_by_id' => null,
            'paid_at' => null,
            'metadata' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }

    public function forSubscription(Subscription $subscription): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $subscription->organization_id,
            'subscription_id' => $subscription->id,
        ]);
    }
}
