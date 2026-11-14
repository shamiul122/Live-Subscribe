<?php

namespace App\Actions\Organizations\Concerns;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

trait EnsuresOrganizationPrivileges
{
    protected function ensureOwner(Organization $organization, User $user): void
    {
        if (! $organization->isOwnedBy($user)) {
            throw new AuthorizationException('Only the organization owner can perform this action.');
        }
    }

    protected function ensureManager(Organization $organization, User $user): void
    {
        if ($organization->isOwnedBy($user)) {
            return;
        }

        $membership = $organization->activeMembershipFor($user);

        if ($membership === null || $membership->role !== OrganizationRole::Admin) {
            throw new AuthorizationException('Only organization owners and admins can perform this action.');
        }
    }

    protected function ensureActiveMember(Organization $organization, User $user): void
    {
        if (! $organization->isOwnedBy($user) && ! $organization->hasMember($user)) {
            throw new AuthorizationException('You are not an active member of this organization.');
        }
    }
}
