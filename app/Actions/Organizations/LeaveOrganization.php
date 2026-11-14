<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveOrganization
{
    /**
     * A member can leave, but the owner must transfer ownership or delete the
     * organization first. Ownership must never be orphaned.
     */
    public function handle(Organization $organization, User $user): void
    {
        if ($organization->isOwnedBy($user)) {
            throw ValidationException::withMessages([
                'organization' => 'Transfer ownership or delete the organization before leaving it.',
            ]);
        }

        $membership = $organization->activeMembershipFor($user);

        if ($membership === null) {
            throw ValidationException::withMessages([
                'organization' => 'You are not an active member of this organization.',
            ]);
        }

        DB::transaction(function () use ($organization, $membership): void {
            $locked = $organization->memberships()
                ->whereKey($membership->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return;
            }

            $locked->delete();
        });
    }
}
