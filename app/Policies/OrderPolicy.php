<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationRole;

class OrderPolicy
{
    use ChecksOrganizationRole;

    /**
     * Payment history is visible to owners and admins.
     */
    public function view(User $user, Order $order): bool
    {
        $organization = $order->organization()->first();

        return $organization !== null && $this->isManager($user, $organization);
    }

    /**
     * Paying an order is a billing mutation and is owner-only.
     */
    public function pay(User $user, Order $order): bool
    {
        $organization = $order->organization()->first();

        return $organization !== null && $this->isOwner($user, $organization);
    }
}
