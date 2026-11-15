<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now();

        return [
            'organization_id' => Organization::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'current_period_start' => $start,
            'current_period_end' => $start->copy()->addMonthNoOverflow(),
            'grace_ends_at' => null,
            'cancel_at_period_end' => false,
            'cancelled_at' => null,
            'suspended_at' => null,
            'ended_at' => null,
            'price_minor' => 1900,
            'currency' => 'USD',
            'billing_interval' => BillingInterval::Month,
            'billing_interval_count' => 1,
            'pending_plan_id' => null,
            'provider' => 'mock',
            'provider_subscription_id' => null,
            'last_renewed_at' => null,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }

    public function forPlan(Plan $plan): static
    {
        return $this->state(fn (): array => [
            'plan_id' => $plan->id,
            'price_minor' => $plan->price_minor,
            'currency' => $plan->currency,
            'billing_interval' => $plan->billing_interval,
            'billing_interval_count' => $plan->billing_interval_count,
        ]);
    }

    public function trialing(?string $endsAt = null): static
    {
        return $this->state(function () use ($endsAt): array {
            $trialEnd = $endsAt !== null ? now()->parse($endsAt) : now()->addDays(14);

            return [
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => $trialEnd,
                'current_period_end' => $trialEnd->copy(),
            ];
        });
    }

    public function pastDue(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::PastDue,
            'grace_ends_at' => now()->addDays(7),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Cancelled,
            'cancel_at_period_end' => true,
            'cancelled_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Expired,
            'ended_at' => now(),
        ]);
    }
}
