<?php

namespace Database\Factories;

use App\Enums\Feature;
use App\Models\Subscription;
use App\Models\UsageRecord;
use Database\Factories\Concerns\ResolvesSubscriptionTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageRecord>
 */
class UsageRecordFactory extends Factory
{
    use ResolvesSubscriptionTenant;

    protected $model = UsageRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now();

        return [
            'feature' => Feature::ApiRequests,
            'period_start' => $start,
            'period_end' => $start->copy()->addMonthNoOverflow(),
            'used' => 0,
            'limit_snapshot' => 1000,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): UsageRecord
    {
        [$subscriptionId, $organizationId] = $this->resolveSubscriptionTenant($attributes);

        $record = parent::newModel($attributes);
        $record->subscription_id = $subscriptionId;
        $record->organization_id = $organizationId;

        return $record;
    }

    public function forSubscription(Subscription $subscription): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $subscription->organization_id,
            'subscription_id' => $subscription->id,
            'period_start' => $subscription->current_period_start,
            'period_end' => $subscription->current_period_end,
        ]);
    }
}
