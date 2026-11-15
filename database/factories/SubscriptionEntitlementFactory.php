<?php

namespace Database\Factories;

use App\Enums\Feature;
use App\Models\Subscription;
use App\Models\SubscriptionEntitlement;
use Database\Factories\Concerns\ResolvesSubscriptionTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionEntitlement>
 */
class SubscriptionEntitlementFactory extends Factory
{
    use ResolvesSubscriptionTenant;

    protected $model = SubscriptionEntitlement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feature' => Feature::ApiRequests,
            'limit' => 1000,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): SubscriptionEntitlement
    {
        [$subscriptionId, $organizationId] = $this->resolveSubscriptionTenant($attributes);

        $entitlement = parent::newModel($attributes);
        $entitlement->subscription_id = $subscriptionId;
        $entitlement->organization_id = $organizationId;

        return $entitlement;
    }

    public function forSubscription(Subscription $subscription): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $subscription->organization_id,
            'subscription_id' => $subscription->id,
        ]);
    }

    public function forFeature(Feature $feature, ?int $limit): static
    {
        return $this->state(fn (): array => [
            'feature' => $feature,
            'limit' => $limit,
        ]);
    }
}
