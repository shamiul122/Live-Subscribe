<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\StartTrialSubscription;
use App\Enums\Feature;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBilling;
use Tests\Concerns\CreatesOrganizations;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use CreatesBilling, CreatesOrganizations, RefreshDatabase;

    public function test_owner_can_start_a_trial_and_gains_entitlements(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $plan = $this->createPlan(['trial_days' => 14], [
            Feature::ApiRequests->value => 1000,
            Feature::Projects->value => 3,
        ]);

        $subscription = app(StartTrialSubscription::class)->handle($organization, $owner, $plan);

        $this->assertSame('trialing', $subscription->status->value);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertTrue($subscription->isEntitledAt(now()));
        $this->assertSame(1000, $subscription->entitlementLimit(Feature::ApiRequests));
    }

    public function test_trial_cannot_be_started_twice(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $plan = $this->createPlan(['trial_days' => 14], [Feature::ApiRequests->value => 1000]);

        app(StartTrialSubscription::class)->handle($organization, $owner, $plan);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(StartTrialSubscription::class)->handle($organization, $owner, $plan);
    }

    public function test_only_the_owner_may_start_a_trial(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $this->addMember($organization, $admin, \App\Enums\OrganizationRole::Admin);

        $plan = $this->createPlan(['trial_days' => 14], [Feature::ApiRequests->value => 1000]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        app(StartTrialSubscription::class)->handle($organization, $admin, $plan);
    }

    public function test_archived_plans_cannot_be_started(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $plan = Plan::factory()->archived()->create();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(StartTrialSubscription::class)->handle($organization, $owner, $plan);
    }
}
