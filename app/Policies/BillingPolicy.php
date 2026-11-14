<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationRole;

/**
 * Organization-scoped billing abilities.
 *
 * The approved permission matrix:
 *  - members view the subscription and usage
 *  - admins additionally view payment history and manage licenses
 *  - the owner manages billing (start, cancel, resume, plan changes, pay)
 *
 * Because only one policy can be registered per model, these abilities are
 * exposed as named gates by BillingServiceProvider while this class keeps the
 * rules in one place.
 */
class BillingPolicy
{
    use ChecksOrganizationRole;

    public function viewSubscription(User $user, Organization $organization): bool
    {
        return $this->isMember($user, $organization);
    }

    public function viewUsage(User $user, Organization $organization): bool
    {
        return $this->isMember($user, $organization);
    }

    public function viewLicenses(User $user, Organization $organization): bool
    {
        return $this->isMember($user, $organization);
    }

    public function viewHistory(User $user, Organization $organization): bool
    {
        return $this->isManager($user, $organization);
    }

    public function manageLicenses(User $user, Organization $organization): bool
    {
        return $this->isManager($user, $organization);
    }

    public function manageBilling(User $user, Organization $organization): bool
    {
        return $this->isOwner($user, $organization);
    }

    public function viewSocialAccounts(User $user, Organization $organization): bool
    {
        return $this->isMember($user, $organization);
    }

    public function manageSocialAccounts(User $user, Organization $organization): bool
    {
        return $this->isManager($user, $organization);
    }
}
