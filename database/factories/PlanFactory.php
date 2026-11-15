<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->sentence(),
            'price_minor' => 1900,
            'currency' => 'USD',
            'billing_interval' => BillingInterval::Month,
            'billing_interval_count' => 1,
            'trial_days' => 0,
            'is_active' => true,
            'archived_at' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * slug is guarded on the model, so it is set explicitly.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): Plan
    {
        $slug = $attributes['slug'] ?? Str::slug((string) ($attributes['name'] ?? 'plan')).'-'.Str::lower(Str::random(6));
        unset($attributes['slug']);

        $plan = parent::newModel($attributes);
        $plan->slug = $slug;

        return $plan;
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }

    public function yearly(): static
    {
        return $this->state(fn (): array => ['billing_interval' => BillingInterval::Year]);
    }

    public function withTrial(int $days = 14): static
    {
        return $this->state(fn (): array => ['trial_days' => $days]);
    }
}
