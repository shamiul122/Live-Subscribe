<?php

namespace Database\Seeders;

use App\Enums\BillingInterval;
use App\Enums\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Deterministic demonstration catalog.
 *
 * These are demo values for local/testing only. Replace them with the real
 * commercial catalog before launch. Seeding is idempotent: running it twice
 * updates the same plans and features instead of duplicating them.
 */
class PlanSeeder extends Seeder
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private const PLANS = [
        [
            'slug' => 'starter',
            'name' => 'Starter',
            'description' => 'For small teams getting started.',
            'price_minor' => 1900,
            'currency' => 'USD',
            'billing_interval' => BillingInterval::Month,
            'billing_interval_count' => 1,
            'trial_days' => 14,
            'sort_order' => 1,
            'features' => [
                Feature::ApiRequests->value => 1000,
                Feature::Projects->value => 3,
                Feature::Licenses->value => 1,
                Feature::ConnectedAccounts->value => 3,
            ],
        ],
        [
            'slug' => 'growth',
            'name' => 'Growth',
            'description' => 'For growing teams that need more headroom.',
            'price_minor' => 4900,
            'currency' => 'USD',
            'billing_interval' => BillingInterval::Month,
            'billing_interval_count' => 1,
            'trial_days' => 14,
            'sort_order' => 2,
            'features' => [
                Feature::ApiRequests->value => 10000,
                Feature::Projects->value => 20,
                Feature::Licenses->value => 5,
                Feature::AuditLog->value => null,
                Feature::ConnectedAccounts->value => 10,
            ],
        ],
        [
            'slug' => 'enterprise',
            'name' => 'Enterprise',
            'description' => 'Annual plan with unlimited usage.',
            'price_minor' => 49900,
            'currency' => 'USD',
            'billing_interval' => BillingInterval::Year,
            'billing_interval_count' => 1,
            'trial_days' => 30,
            'sort_order' => 3,
            'features' => [
                Feature::ApiRequests->value => null,
                Feature::Projects->value => null,
                Feature::Licenses->value => 25,
                Feature::AuditLog->value => null,
                Feature::ConnectedAccounts->value => null,
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::PLANS as $definition) {
            $features = $definition['features'];
            unset($definition['features']);

            $plan = Plan::updateOrCreate(
                ['slug' => $definition['slug']],
                $definition,
            );

            foreach ($features as $featureValue => $limit) {
                $feature = Feature::from($featureValue);

                $plan->features()->updateOrCreate(
                    ['feature' => $feature->value],
                    [
                        'feature_type' => $feature->type(),
                        'limit' => $limit,
                    ],
                );
            }

            // Remove features that are no longer part of the plan definition.
            $plan->features()
                ->whereNotIn('feature', array_keys($features))
                ->delete();
        }
    }
}
