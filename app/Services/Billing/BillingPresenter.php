<?php

namespace App\Services\Billing;

use App\Enums\Feature;
use App\Models\License;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Billing\Money;

/**
 * Builds the presentation arrays shared by billing controllers. Keeping the
 * shape in one place means every page renders money, limits and dates the same
 * way and the frontend types stay stable.
 */
final class BillingPresenter
{
    public function __construct(private readonly EntitlementService $entitlements)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function planSummary(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'slug' => $plan->slug,
            'name' => $plan->name,
            'description' => $plan->description,
            'price' => $this->money($plan->price_minor, $plan->currency),
            'interval' => [
                'value' => $plan->billing_interval->value,
                'label' => $plan->billing_interval->label(),
                'count' => $plan->billing_interval_count,
                'label_full' => $plan->intervalLabel(),
            ],
            'trial_days' => $plan->trial_days,
            'has_trial' => $plan->hasTrial(),
            'is_active' => $plan->is_active,
            'is_archived' => $plan->isArchived(),
            'is_purchasable' => $plan->isPurchasable(),
            'features' => $plan->features->map(fn ($feature): array => [
                'feature' => $feature->feature->value,
                'label' => $feature->feature->label(),
                'type' => $feature->feature_type->value,
                'limit' => $feature->limit,
                'limit_label' => $this->limitLabel($feature->feature, $feature->limit),
                'included' => $feature->limit !== 0,
                'unlimited' => $feature->limit === null,
                'unit' => $feature->feature->unit(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function subscriptionSummary(Subscription $subscription): array
    {
        $plan = $subscription->plan()->first();

        return [
            'id' => $subscription->id,
            'status' => $subscription->status->value,
            'status_label' => $subscription->status->label(),
            'is_trialing' => $subscription->status->value === 'trialing',
            'is_active' => $subscription->status->value === 'active',
            'is_past_due' => $subscription->status->value === 'past_due',
            'is_cancelled' => $subscription->status->value === 'cancelled',
            'is_suspended' => $subscription->status->value === 'suspended',
            'is_expired' => $subscription->status->value === 'expired',
            'is_entitled' => $subscription->isEntitledAt(now()),
            'plan' => $plan === null ? null : [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'name' => $plan->name,
            ],
            'price' => $this->money($subscription->price_minor, $subscription->currency),
            'interval' => [
                'value' => $subscription->billing_interval->value,
                'label' => $subscription->billing_interval->label(),
                'count' => $subscription->billing_interval_count,
            ],
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'current_period_start' => $subscription->current_period_start->toIso8601String(),
            'current_period_end' => $subscription->current_period_end->toIso8601String(),
            'grace_ends_at' => $subscription->grace_ends_at?->toIso8601String(),
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
            'suspended_at' => $subscription->suspended_at?->toIso8601String(),
            'ended_at' => $subscription->ended_at?->toIso8601String(),
            'entitled_until' => $subscription->entitledUntil()?->toIso8601String(),
            'pending_plan' => $this->pendingPlan($subscription),
            'days_remaining' => $this->daysRemaining($subscription),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function featureLines(Organization $organization, Subscription $subscription): array
    {
        return $subscription->entitlements()
            ->get()
            ->map(function ($entitlement) use ($organization): array {
                $feature = $entitlement->feature;
                $used = $this->entitlements->usage($organization, $feature);

                return $this->featureLine($feature, $entitlement->limit, $used);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function featureLine(Feature $feature, ?int $limit, int $used): array
    {
        return [
            'feature' => $feature->value,
            'label' => $feature->label(),
            'type' => $feature->type()->value,
            'limit' => $limit,
            'limit_label' => $this->limitLabel($feature, $limit),
            'used' => $used,
            'used_label' => number_format($used),
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'remaining_label' => $limit === null ? 'Unlimited' : number_format(max(0, $limit - $used)),
            'unlimited' => $limit === null,
            'included' => $limit !== 0,
            'exhausted' => $limit !== null && $limit > 0 && $used >= $limit,
            'unit' => $feature->unit(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function orderSummary(Order $order): array
    {
        $plan = $order->plan()->first();

        return [
            'id' => $order->id,
            'type' => $order->type,
            'type_label' => $order->typeLabel(),
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'is_payable' => $order->isPayable(),
            'amount' => $this->money($order->amount_minor, $order->currency),
            'plan' => $plan === null ? null : [
                'slug' => $plan->slug,
                'name' => $plan->name,
            ],
            'paid_at' => $order->paid_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'transactions' => $order->transactions
                ->map(fn ($transaction): array => [
                    'id' => $transaction->id,
                    'status' => $transaction->status->value,
                    'status_label' => $transaction->status->label(),
                    'provider' => $transaction->provider,
                    'provider_reference' => $transaction->provider_reference,
                    'amount' => $this->money($transaction->amount_minor, $transaction->currency),
                    'failure_code' => $transaction->failure_code,
                    'failure_message' => $transaction->failure_message,
                    'processed_at' => $transaction->processed_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function licenseSummary(License $license): array
    {
        return [
            'id' => $license->id,
            'label' => $license->label,
            'masked_key' => $license->maskedKey(),
            'status' => $license->status->value,
            'status_label' => $license->status->label(),
            'is_active' => $license->status->value === 'active',
            'is_usable' => $license->isUsableAt(now()),
            'activated_at' => $license->activated_at?->toIso8601String(),
            'expires_at' => $license->expires_at?->toIso8601String(),
            'suspended_at' => $license->suspended_at?->toIso8601String(),
            'revoked_at' => $license->revoked_at?->toIso8601String(),
            'created_at' => $license->created_at?->toIso8601String(),
        ];
    }

    public function limitLabel(Feature $feature, ?int $limit): string
    {
        if ($limit === null) {
            return 'Unlimited';
        }

        if ($limit === 0) {
            return 'Not included';
        }

        return number_format($limit).' '.$feature->unit();
    }

    /**
     * @return array<string, mixed>
     */
    private function money(int $minor, string $currency): array
    {
        return [
            'minor' => $minor,
            'currency' => strtoupper($currency),
            'formatted' => Money::format($minor, $currency),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pendingPlan(Subscription $subscription): ?array
    {
        if ($subscription->pending_plan_id === null) {
            return null;
        }

        $plan = $subscription->pendingPlan()->first();

        if ($plan === null) {
            return null;
        }

        return [
            'id' => $plan->id,
            'slug' => $plan->slug,
            'name' => $plan->name,
        ];
    }

    private function daysRemaining(Subscription $subscription): ?int
    {
        $until = $subscription->entitledUntil();

        if ($until === null) {
            return null;
        }

        return max(0, (int) ceil(now()->diffInSeconds($until, false) / 86400));
    }
}
