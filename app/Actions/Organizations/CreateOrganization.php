<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrganization
{
    /**
     * Create an organization and the owner's active admin membership in one
     * transaction. An organization never exists without its owner membership.
     */
    public function handle(User $owner, string $name): Organization
    {
        $name = trim($name);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'The organization name is required.',
            ]);
        }

        return DB::transaction(function () use ($owner, $name): Organization {
            $organization = new Organization([
                'name' => $name,
                'slug' => OrganizationSlug::unique($name),
            ]);

            $organization->owner()->associate($owner);
            $organization->save();

            $membership = new OrganizationMembership([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
                'status' => OrganizationMembershipStatus::Active,
                'joined_at' => now(),
            ]);

            $membership->forceFill(['role' => OrganizationRole::Admin]);
            $membership->save();

            return $organization;
        });
    }
}
