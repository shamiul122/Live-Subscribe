<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\EnsuresBillingPrivileges;
use App\Actions\Billing\Concerns\ManagesSubscriptionState;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Start a trial subscription for an organization.
 *
 * A trial is granted at most once per organization. The trial window becomes
 * the first billing period so entitlement checks and renewal use a single
 * period model.
 */
class StartTrialSubscription
{
    use EnsuresBillingPrivileges, ManagesSubscriptionState;

    public function handle(Organization $organization, User $actor, Plan $plan): Subscription
    {
        $this->ensureBillingOwner($organization, $actor);

        if (! $plan->isPurchasable()) {
            throw ValidationException::withMessages([
                'plan' => 'This plan is not available.',
            ]);
        }

        if (! $plan->hasTrial()) {
            throw ValidationException::withMessages([
                'plan' => 'This plan does not include a trial.',
            ]);
        }

        if ($organization->hasUsedTrial()) {
            throw ValidationException::withMessages([
                'plan' => 'This organization has already used its trial.',
            ]);
        }

        return DB::transaction(function () use ($organization, $actor, $plan): Subscription {
            $locked = Organization::query()
                ->whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureOrganizationHasNoNonTerminalSubscription($locked->id);

            $trialEndsAt = now()->addDays($plan->trial_days);

            $subscription = new Subscription([
                'organization_id' => $locked->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => $trialEndsAt,
                'current_period_start' => now(),
                'current_period_end' => $trialEndsAt->copy(),
                'grace_ends_at' => null,
                'cancel_at_period_end' => false,
                'price_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                'billing_interval' => $plan->billing_interval,
                'billing_interval_count' => $plan->billing_interval_count,
                'provider' => (string) config('billing.gateway', 'mock'),
            ]);

            $subscription->save();

            $this->snapshotEntitlements($subscription, $plan);

            $this->recordEvent($subscription, SubscriptionEventType::Created, $actor, null, SubscriptionStatus::Trialing, [
                'plan' => $plan->slug,
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::TrialStarted, $actor, null, SubscriptionStatus::Trialing, [
                'trial_ends_at' => $trialEndsAt->toIso8601String(),
                'trial_days' => $plan->trial_days,
            ]);

            app(EntitlementService::class)->forget($locked);

            return $subscription->refresh();
        });
    }
}
