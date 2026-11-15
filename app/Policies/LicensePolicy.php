<?php

namespace App\Policies;

use App\Models\License;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationRole;

class LicensePolicy
{
    use ChecksOrganizationRole;

    public function view(User $user, License $license): bool
    {
        $organization = $license->organization()->first();

        return $organization !== null && $this->isMember($user, $organization);
    }

    /**
     * Licenses are managed by owners and admins.
     */
    public function manage(User $user, License $license): bool
    {
        $organization = $license->organization()->first();

        return $organization !== null && $this->isManager($user, $organization);
    }
}
