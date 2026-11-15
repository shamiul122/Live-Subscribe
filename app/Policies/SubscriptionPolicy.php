<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationRole;

class SubscriptionPolicy
{
    use ChecksOrganizationRole;

    public function view(User $user, Subscription $subscription): bool
    {
        $organization = $subscription->organization()->first();

        return $organization !== null && $this->isMember($user, $organization);
    }

    /**
     * Billing mutations are owner-only.
     */
    public function manage(User $user, Subscription $subscription): bool
    {
        $organization = $subscription->organization()->first();

        return $organization !== null && $this->isOwner($user, $organization);
    }

    public function manageLicenses(User $user, Subscription $subscription): bool
    {
        $organization = $subscription->organization()->first();

        return $organization !== null && $this->isManager($user, $organization);
    }
}
