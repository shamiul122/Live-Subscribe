<?php

namespace Tests\Concerns;

use App\Enums\Feature;
use App\Models\License;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Billing\LicenseKey;

trait CreatesBilling
{
    /**
     * Create a plan with a deterministic feature catalog.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int|string, int|null>  $features
     */
    protected function createPlan(array $attributes = [], array $features = []): Plan
    {
        $plan = Plan::factory()->create($attributes);

        foreach ($features as $feature => $limit) {
            $enum = $feature instanceof Feature ? $feature : Feature::from((string) $feature);

            $plan->features()->create([
                'feature' => $enum->value,
                'feature_type' => $enum->type(),
                'limit' => $limit,
            ]);
        }

        return $plan->refresh();
    }

    /**
     * Create a subscription and snapshot its entitlements from the plan,
     * bypassing the payment gateway.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function subscribe(Organization $organization, Plan $plan, array $attributes = []): Subscription
    {
        $subscription = Subscription::factory()
            ->forOrganization($organization)
            ->forPlan($plan)
            ->create($attributes);

        foreach ($plan->features()->get() as $feature) {
            $subscription->entitlements()->create([
                'organization_id' => $organization->id,
                'feature' => $feature->feature->value,
                'limit' => $feature->limit,
            ]);
        }

        return $subscription->refresh();
    }

    /**
     * Create an active license for a subscription.
     */
    protected function createLicense(Subscription $subscription, array $attributes = []): License
    {
        $raw = LicenseKey::generate();

        $license = new License(array_merge([
            'organization_id' => $subscription->organization_id,
            'subscription_id' => $subscription->id,
            'key_prefix' => LicenseKey::prefix($raw),
            'key_last_four' => LicenseKey::lastFour($raw),
            'label' => 'Test license',
            'status' => \App\Enums\LicenseStatus::Active,
            'expires_at' => $subscription->current_period_end,
        ], $attributes));

        $license->key_hash = LicenseKey::hash($raw);
        $license->save();

        return $license;
    }
}
