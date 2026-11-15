<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Billing\CancelScheduledPlanChange;
use App\Actions\Billing\CancelSubscription;
use App\Actions\Billing\ReinstateSubscription;
use App\Actions\Billing\ResumeSubscription;
use App\Actions\Billing\SchedulePlanChange;
use App\Actions\Billing\StartTrialSubscription;
use App\Actions\Billing\SuspendSubscription;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ChangePlanRequest;
use App\Http\Requests\Billing\StartTrialRequest;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\BillingPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(private readonly BillingPresenter $presenter)
    {
    }

    public function show(Request $request, Organization $organization): Response
    {
        Gate::authorize('billing.viewSubscription', $organization);

        $user = $request->user();
        $subscription = $organization->currentSubscription()->first();

        $events = [];
        $features = [];
        $summary = null;

        if ($subscription !== null) {
            $summary = $this->presenter->subscriptionSummary($subscription);
            $features = $this->presenter->featureLines($organization, $subscription);

            $events = $subscription->events()
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn ($event): array => [
                    'id' => $event->id,
                    'type' => $event->type->value,
                    'type_label' => $event->type->label(),
                    'from_status' => $event->from_status?->value,
                    'to_status' => $event->to_status?->value,
                    'data' => $event->data,
                    'created_at' => $event->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        $availablePlans = Plan::query()
            ->purchasable()
            ->with('features')
            ->orderBy('sort_order')
            ->orderBy('price_minor')
            ->get()
            ->reject(fn (Plan $plan): bool => $subscription !== null
                && ($plan->id === $subscription->plan_id || $plan->id === $subscription->pending_plan_id))
            ->map(fn (Plan $plan): array => $this->presenter->planSummary($plan))
            ->values();

        return Inertia::render('organizations/subscription/Show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'subscription' => $summary,
            'features' => $features,
            'events' => $events,
            'available_plans' => $availablePlans,
            'can_start_trial' => $subscription === null
                && $user->can('billing.manageBilling', $organization)
                && ! $organization->hasUsedTrial(),
            'has_used_trial' => $organization->hasUsedTrial(),
            'permissions' => [
                'manage' => $user->can('billing.manageBilling', $organization),
                'manage_licenses' => $user->can('billing.manageLicenses', $organization),
                'view_history' => $user->can('billing.viewHistory', $organization),
            ],
        ]);
    }

    public function startTrial(StartTrialRequest $request, Organization $organization, StartTrialSubscription $startTrial): RedirectResponse
    {
        $plan = Plan::query()->where('slug', $request->validated('plan'))->firstOrFail();

        $startTrial->handle($organization, $request->user(), $plan);

        return redirect()
            ->route('organizations.subscription.show', $organization)
            ->with('status', 'Your free trial has started.');
    }

    public function cancel(Request $request, Organization $organization, CancelSubscription $cancel): RedirectResponse
    {
        Gate::authorize('billing.manageBilling', $organization);

        $subscription = $this->requireSubscription($organization);

        $cancel->handle($subscription, $request->user());

        return redirect()
            ->route('organizations.subscription.show', $organization)
            ->with('status', 'Your subscription will end at the end of the current period.');
    }

    public function resume(Request $request, Organization $organization, ResumeSubscription $resume): RedirectResponse
    {
        Gate::authorize('billing.manageBilling', $organization);

        $subscription = $this->requireSubscription($organization);

        $resume->handle($subscription, $request->user());

        return redirect()
            ->route('organizations.subscription.show', $organization)
            ->with('status', 'Your subscription has been resumed.');
    }

    public function changePlan(ChangePlanRequest $request, Organization $organization, SchedulePlanChange $schedule): RedirectResponse
    {
        $subscription = $this->requireSubscription($organization);
        $plan = Plan::query()->where('slug', $request->validated('plan'))->firstOrFail();

        $schedule->handle($subscription, $request->user(), $plan);

        return redirect()
            ->route('organizations.subscription.show', $organization)
            ->with('status', 'The plan change is scheduled for your next renewal.');
    }

    public function cancelPlanChange(Request $request, Organization $organization, CancelScheduledPlanChange $cancel): RedirectResponse
    {
        Gate::authorize('billing.manageBilling', $organization);

        $subscription = $this->requireSubscription($organization);

        $cancel->handle($subscription, $request->user());

        return redirect()
            ->route('organizations.subscription.show', $organization)
            ->with('status', 'The scheduled plan change has been cancelled.');
    }

    public function suspend(Request $request, Organization $organization, SuspendSubscription $suspend): RedirectResponse
    {
        Gate::authorize('billing.manageLicenses', $organization);

        $subscription = $this->requireSubscription($organization);

        $suspend->handle($subscription, $request->user());

        return redirect()
            ->route('organizations.subscription.show', $organization)
            ->with('status', 'The subscription has been suspended.');
    }

    public function reinstate(Request $request, Organization $organization, ReinstateSubscription $reinstate): RedirectResponse
    {
        Gate::authorize('billing.manageLicenses', $organization);

        $subscription = $this->requireSubscription($organization);

        $reinstate->handle($subscription, $request->user());

        return redirect()
            ->route('organizations.subscription.show', $organization)
            ->with('status', 'The subscription has been reinstated.');
    }

    public function history(Request $request, Organization $organization): Response
    {
        Gate::authorize('billing.viewHistory', $organization);

        $orders = $organization->orders()
            ->with(['plan', 'transactions'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn ($order): array => $this->presenter->orderSummary($order))
            ->values();

        return Inertia::render('organizations/subscription/History', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'orders' => $orders,
        ]);
    }

    private function requireSubscription(Organization $organization): Subscription
    {
        $subscription = $organization->currentSubscription()->first();

        abort_if($subscription === null, 404, 'This organization does not have a subscription.');

        return $subscription;
    }
}
