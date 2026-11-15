<?php

namespace App\Policies\Concerns;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

/**
 * Shared organization role checks for the billing policies. This mirrors the
 * Step 2 privilege model: owner, admin (manager) and member.
 */
trait ChecksOrganizationRole
{
    protected function isMember(User $user, Organization $organization): bool
    {
        return $organization->isOwnedBy($user) || $organization->hasMember($user);
    }

    protected function isManager(User $user, Organization $organization): bool
    {
        if ($organization->isOwnedBy($user)) {
            return true;
        }

        return $organization->activeMembershipFor($user)?->role === OrganizationRole::Admin;
    }

    protected function isOwner(User $user, Organization $organization): bool
    {
        return $organization->isOwnedBy($user);
    }
}
