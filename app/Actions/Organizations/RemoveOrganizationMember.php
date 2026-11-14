<?php

namespace App\Actions\Organizations;

use App\Actions\Organizations\Concerns\EnsuresOrganizationPrivileges;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveOrganizationMember
{
    use EnsuresOrganizationPrivileges;

    public function handle(
        Organization $organization,
        OrganizationMembership $membership,
        User $actor,
    ): void {
        $this->ensureManager($organization, $actor);

        if ($membership->organization_id !== $organization->id) {
            throw ValidationException::withMessages([
                'membership' => 'The member does not belong to this organization.',
            ]);
        }

        if ($membership->user_id === $organization->owner_id) {
            throw ValidationException::withMessages([
                'membership' => 'The organization owner cannot be removed.',
            ]);
        }

        if ($membership->user_id === $actor->id) {
            throw ValidationException::withMessages([
                'membership' => 'Use the leave action to remove yourself from an organization.',
            ]);
        }

        if ($membership->role === OrganizationRole::Admin && ! $organization->isOwnedBy($actor)) {
            throw new AuthorizationException('Only the organization owner can remove an admin.');
        }

        DB::transaction(function () use ($organization, $membership): void {
            $locked = OrganizationMembership::whereKey($membership->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return;
            }

            if ($locked->user_id === $organization->owner_id) {
                throw ValidationException::withMessages([
                    'membership' => 'The organization owner cannot be removed.',
                ]);
            }

            $locked->delete();
        });
    }
}
