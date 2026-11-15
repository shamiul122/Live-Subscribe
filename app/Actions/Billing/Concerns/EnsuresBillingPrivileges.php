<?php

namespace App\Actions\Billing\Concerns;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Billing authorization mirrors the Step 2 organization privilege model.
 * Billing mutations are owner-only; license management is owner/admin.
 */
trait EnsuresBillingPrivileges
{
    protected function ensureBillingOwner(Organization $organization, User $actor): void
    {
        if (! $organization->isOwnedBy($actor)) {
            throw new AuthorizationException('Only the organization owner can manage billing.');
        }
    }

    protected function ensureBillingManager(Organization $organization, User $actor): void
    {
        if ($organization->isOwnedBy($actor)) {
            return;
        }

        $membership = $organization->activeMembershipFor($actor);

        if ($membership === null || $membership->role !== OrganizationRole::Admin) {
            throw new AuthorizationException('Only organization owners and admins can manage licenses.');
        }
    }

    protected function ensureBillingMember(Organization $organization, User $actor): void
    {
        if (! $organization->isOwnedBy($actor) && ! $organization->hasMember($actor)) {
            throw new AuthorizationException('You are not an active member of this organization.');
        }
    }
}
