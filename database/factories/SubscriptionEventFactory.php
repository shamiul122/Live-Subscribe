<?php

namespace Database\Factories;

use App\Enums\SubscriptionEventType;
use App\Models\SubscriptionEvent;
use Database\Factories\Concerns\ResolvesSubscriptionTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionEvent>
 */
class SubscriptionEventFactory extends Factory
{
    use ResolvesSubscriptionTenant;

    protected $model = SubscriptionEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => SubscriptionEventType::Created,
            'from_status' => null,
            'to_status' => null,
            'actor_id' => null,
            'data' => null,
            'created_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): SubscriptionEvent
    {
        [$subscriptionId, $organizationId] = $this->resolveSubscriptionTenant($attributes);

        $event = parent::newModel($attributes);
        $event->subscription_id = $subscriptionId;
        $event->organization_id = $organizationId;

        return $event;
    }
}
