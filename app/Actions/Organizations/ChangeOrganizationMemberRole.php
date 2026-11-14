<?php

namespace App\Actions\Organizations;

use App\Actions\Organizations\Concerns\EnsuresOrganizationPrivileges;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeOrganizationMemberRole
{
    use EnsuresOrganizationPrivileges;

    /**
     * Role changes are owner-only. Admins may manage ordinary members but can
     * never grant or revoke the admin role, preventing privilege escalation.
     */
    public function handle(
        Organization $organization,
        OrganizationMembership $membership,
        User $actor,
        OrganizationRole $role,
    ): void {
        $this->ensureOwner($organization, $actor);

        if ($membership->organization_id !== $organization->id) {
            throw ValidationException::withMessages([
                'membership' => 'The member does not belong to this organization.',
            ]);
        }

        if (! $membership->isActive()) {
            throw ValidationException::withMessages([
                'membership' => 'Only active members can have their role changed.',
            ]);
        }

        if ($membership->user_id === $organization->owner_id) {
            throw ValidationException::withMessages([
                'membership' => 'The organization owner always has the admin role.',
            ]);
        }

        DB::transaction(function () use ($organization, $membership, $role): void {
            $locked = OrganizationMembership::whereKey($membership->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->organization_id !== $organization->id) {
                throw ValidationException::withMessages([
                    'membership' => 'The member does not belong to this organization.',
                ]);
            }

            if ($locked->user_id === $organization->owner_id) {
                throw ValidationException::withMessages([
                    'membership' => 'The organization owner always has the admin role.',
                ]);
            }

            $locked->forceFill(['role' => $role])->save();
        });
    }
}
