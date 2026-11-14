<?php

namespace Tests\Concerns;

use App\Actions\Organizations\CreateOrganization;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

trait CreatesOrganizations
{
    /**
     * Create an organization through the domain action so the owner membership
     * invariants always hold in tests.
     */
    protected function createOrganization(User $owner, string $name = 'Acme'): Organization
    {
        return app(CreateOrganization::class)->handle($owner, $name);
    }

    /**
     * Add a membership directly, bypassing the guarded role attribute.
     */
    protected function addMember(
        Organization $organization,
        User $user,
        OrganizationRole $role = OrganizationRole::Member,
        OrganizationMembershipStatus $status = OrganizationMembershipStatus::Active,
    ): OrganizationMembership {
        $membership = new OrganizationMembership([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => $status,
            'joined_at' => now(),
        ]);

        $membership->forceFill(['role' => $role]);
        $membership->save();

        return $membership;
    }
}
