<?php

namespace Database\Factories;

use App\Enums\Feature;
use App\Models\UsageEvent;
use Database\Factories\Concerns\ResolvesSubscriptionTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UsageEvent>
 */
class UsageEventFactory extends Factory
{
    use ResolvesSubscriptionTenant;

    protected $model = UsageEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feature' => Feature::ApiRequests,
            'period_start' => now(),
            'idempotency_key' => (string) Str::uuid(),
            'units' => 1,
            'created_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): UsageEvent
    {
        [$subscriptionId, $organizationId] = $this->resolveSubscriptionTenant($attributes);

        $event = parent::newModel($attributes);
        $event->subscription_id = $subscriptionId;
        $event->organization_id = $organizationId;

        return $event;
    }
}
