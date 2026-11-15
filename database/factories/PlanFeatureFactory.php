<?php

namespace Database\Factories;

use App\Enums\Feature;
use App\Enums\FeatureType;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanFeature>
 */
class PlanFeatureFactory extends Factory
{
    protected $model = PlanFeature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $feature = fake()->randomElement(Feature::cases());

        return [
            'plan_id' => Plan::factory(),
            'feature' => $feature,
            'feature_type' => $feature->type(),
            'limit' => $feature->type() === FeatureType::Boolean ? null : 100,
        ];
    }

    public function forFeature(Feature $feature, ?int $limit = null): static
    {
        return $this->state(fn (): array => [
            'feature' => $feature,
            'feature_type' => $feature->type(),
            'limit' => $limit,
        ]);
    }
}
