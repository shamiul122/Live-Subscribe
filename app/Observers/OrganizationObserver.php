<?php

namespace App\Observers;

use App\Actions\Billing\TerminateOrganizationBilling;
use App\Models\Organization;
use App\Services\Billing\EntitlementService;

/**
 * Keeps billing consistent with the organization lifecycle without changing
 * the Step 2 organization actions.
 */
class OrganizationObserver
{
    public function __construct(private readonly TerminateOrganizationBilling $terminateBilling)
    {
    }

    /**
     * Soft deleting an organization terminates all of its billing.
     */
    public function deleted(Organization $organization): void
    {
        $this->terminateBilling->handle($organization);
    }

    /**
     * Restoring an organization restores membership history only. Billing is
     * never reactivated automatically; the entitlement cache is dropped so the
     * organization is treated as having no subscription.
     */
    public function restored(Organization $organization): void
    {
        app(EntitlementService::class)->forget($organization);
    }
}
