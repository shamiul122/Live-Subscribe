<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferOrganizationOwnership
{
    /**
     * Transfer ownership to an existing active member. The previous owner stays
     * an active admin member of the organization.
     */
    public function handle(Organization $organization, User $actor, User $newOwner): void
    {
        if (! $organization->isOwnedBy($actor)) {
            throw new AuthorizationException('Only the organization owner can transfer ownership.');
        }

        if ($actor->id === $newOwner->id) {
            throw ValidationException::withMessages([
                'email' => 'You already own this organization.',
            ]);
        }

        DB::transaction(function () use ($organization, $actor, $newOwner): void {
            $lockedOrganization = Organization::whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrganization->owner_id !== $actor->id) {
                throw new AuthorizationException('Only the organization owner can transfer ownership.');
            }

            $newOwnerMembership = $lockedOrganization->memberships()
                ->where('user_id', $newOwner->id)
                ->where('status', OrganizationMembershipStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if ($newOwnerMembership === null) {
                throw ValidationException::withMessages([
                    'email' => 'Ownership can only be transferred to an active member.',
                ]);
            }

            $newOwnerMembership->forceFill([
                'role' => OrganizationRole::Admin,
            ])->save();

            $oldOwnerMembership = $lockedOrganization->memberships()
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if ($oldOwnerMembership === null) {
                $oldOwnerMembership = new OrganizationMembership([
                    'organization_id' => $lockedOrganization->id,
                    'user_id' => $actor->id,
                    'status' => OrganizationMembershipStatus::Active,
                    'joined_at' => now(),
                ]);

                $oldOwnerMembership->forceFill(['role' => OrganizationRole::Admin]);
                $oldOwnerMembership->save();
            } elseif (! $oldOwnerMembership->isActive()) {
                $oldOwnerMembership->forceFill([
                    'role' => OrganizationRole::Admin,
                    'status' => OrganizationMembershipStatus::Active,
                    'joined_at' => now(),
                ])->save();
            }

            $lockedOrganization->forceFill(['owner_id' => $newOwner->id])->save();
        });
    }
}
