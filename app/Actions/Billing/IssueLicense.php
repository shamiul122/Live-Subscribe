<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\EnsuresBillingPrivileges;
use App\Enums\EntitlementReason;
use App\Enums\Feature;
use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use App\Support\Billing\LicenseKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issue a license key for an organization.
 *
 * The raw key is generated in memory, hashed with SHA-256 and only the hash is
 * persisted. The caller receives the plain key exactly once.
 *
 * License capacity is a resource entitlement, so the count is checked inside a
 * transaction against the subscription row. Combined with the entitlement
 * check this prevents concurrent issuance from exceeding the plan limit.
 */
class IssueLicense
{
    use EnsuresBillingPrivileges;

    public function __construct(private readonly EntitlementService $entitlements)
    {
    }

    public function handle(Organization $organization, User $actor, ?string $label = null): License
    {
        $this->ensureBillingManager($organization, $actor);

        $subscription = $organization->currentSubscription()->first();

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'license' => 'This organization does not have an active subscription.',
            ]);
        }

        return DB::transaction(function () use ($organization, $actor, $label, $subscription): License {
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            $decision = $this->entitlements->check($actor, $organization, Feature::Licenses);

            if (! $decision->isAllowed()) {
                throw ValidationException::withMessages([
                    'license' => $decision->reason->label(),
                ]);
            }

            $limit = $locked->entitlementLimit(Feature::Licenses);

            $current = License::query()
                ->where('subscription_id', $locked->id)
                ->where('status', LicenseStatus::Active->value)
                ->count();

            if ($limit !== null && $current + 1 > $limit) {
                throw ValidationException::withMessages([
                    'license' => EntitlementReason::ResourceLimitReached->label(),
                ]);
            }

            $raw = LicenseKey::generate();

            $license = new License([
                'organization_id' => $locked->organization_id,
                'subscription_id' => $locked->id,
                'key_prefix' => LicenseKey::prefix($raw),
                'key_last_four' => LicenseKey::lastFour($raw),
                'label' => $label,
                'status' => LicenseStatus::Active,
                'activated_at' => now(),
                'expires_at' => $locked->current_period_end,
                'created_by_id' => $actor->id,
            ]);

            $license->key_hash = LicenseKey::hash($raw);
            $license->save();

            $license->plainKey = $raw;

            return $license;
        });
    }
}
