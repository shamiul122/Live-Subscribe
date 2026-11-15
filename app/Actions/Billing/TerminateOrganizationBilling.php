<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\ManagesSubscriptionState;
use App\Enums\LicenseStatus;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\License;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subscription;
use App\Services\Billing\EntitlementService;
use Illuminate\Support\Facades\DB;

/**
 * Terminate all billing for a soft-deleted organization.
 *
 * Subscriptions expire, licenses are revoked, pending orders are cancelled and
 * events are written. Restoring the organization does not reactivate anything:
 * the owner must start a new subscription.
 */
class TerminateOrganizationBilling
{
    use ManagesSubscriptionState;

    public function handle(Organization $organization): void
    {
        DB::transaction(function () use ($organization): void {
            $subscriptions = Subscription::query()
                ->where('organization_id', $organization->id)
                ->where('status', '!=', SubscriptionStatus::Expired->value)
                ->lockForUpdate()
                ->get();

            foreach ($subscriptions as $subscription) {
                $from = $subscription->status;

                $subscription->fill([
                    'status' => SubscriptionStatus::Expired,
                    'ended_at' => now(),
                    'cancel_at_period_end' => false,
                ]);

                $subscription->save();

                $this->recordEvent($subscription, SubscriptionEventType::OrganizationDeleted, null, $from, SubscriptionStatus::Expired);
            }

            License::query()
                ->where('organization_id', $organization->id)
                ->where('status', '!=', LicenseStatus::Revoked->value)
                ->update([
                    'status' => LicenseStatus::Revoked->value,
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            Order::query()
                ->where('organization_id', $organization->id)
                ->where('status', OrderStatus::Pending->value)
                ->update([
                    'status' => OrderStatus::Cancelled->value,
                    'updated_at' => now(),
                ]);

            app(EntitlementService::class)->forget($organization);
        });
    }
}
