<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Any active member (the owner is always active) may view an organization.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $organization->isOwnedBy($user) || $organization->hasMember($user);
    }

    /**
     * Owners and admins may update organization details.
     */
    public function update(User $user, Organization $organization): bool
    {
        return $this->isManager($user, $organization);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $organization->isOwnedBy($user);
    }

    public function transferOwnership(User $user, Organization $organization): bool
    {
        return $organization->isOwnedBy($user);
    }

    public function inviteMember(User $user, Organization $organization): bool
    {
        return $this->isManager($user, $organization);
    }

    public function revokeInvitation(User $user, Organization $organization): bool
    {
        return $this->isManager($user, $organization);
    }

    /**
     * Role changes are strictly owner-only to prevent admins from escalating
     * privileges.
     */
    public function updateMemberRole(User $user, Organization $organization): bool
    {
        return $organization->isOwnedBy($user);
    }

    public function removeMember(User $user, Organization $organization): bool
    {
        return $this->isManager($user, $organization);
    }

    /**
     * The owner may never leave; it must transfer ownership or delete the
     * organization first.
     */
    public function leave(User $user, Organization $organization): bool
    {
        return ! $organization->isOwnedBy($user) && $organization->hasMember($user);
    }

    private function isManager(User $user, Organization $organization): bool
    {
        if ($organization->isOwnedBy($user)) {
            return true;
        }

        return $organization->activeMembershipFor($user)?->role === OrganizationRole::Admin;
    }
}
