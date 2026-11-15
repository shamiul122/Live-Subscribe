<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\EnsuresBillingPrivileges;
use App\Actions\Billing\Concerns\ManagesSubscriptionState;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Create a pending order for a plan.
 *
 * The amount and currency are always derived from the plan on the server and
 * never trusted from the browser. A caller-supplied idempotency key makes the
 * creation safe to retry; the unique index on orders.idempotency_key is the
 * final guard.
 */
class PlaceOrder
{
    use EnsuresBillingPrivileges, ManagesSubscriptionState;

    public const TYPE_NEW = 'new';

    public const TYPE_RENEWAL = 'renewal';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        Organization $organization,
        Plan $plan,
        ?User $actor = null,
        string $type = self::TYPE_NEW,
        ?Subscription $subscription = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): Order {
        if ($actor !== null && $type !== self::TYPE_RENEWAL) {
            $this->ensureBillingOwner($organization, $actor);
        }

        if (! $plan->isPurchasable()) {
            throw ValidationException::withMessages([
                'plan' => 'This plan is not available.',
            ]);
        }

        if ($subscription !== null && $subscription->organization_id !== $organization->id) {
            throw ValidationException::withMessages([
                'plan' => 'The subscription does not belong to this organization.',
            ]);
        }

        if ($type === self::TYPE_NEW) {
            $this->ensureOrganizationHasNoNonTerminalSubscription($organization->id);
        }

        $key = $idempotencyKey ?? (string) Str::uuid();

        /** @var Order $order */
        $order = Order::query()->firstOrCreate(
            ['idempotency_key' => $key],
            [
                'organization_id' => $organization->id,
                'subscription_id' => $subscription?->id,
                'plan_id' => $plan->id,
                'type' => $type,
                'status' => OrderStatus::Pending,
                'amount_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                'placed_by_id' => $actor?->id,
                'metadata' => $metadata === [] ? null : $metadata,
            ],
        );

        return $order;
    }
}
