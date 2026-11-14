<?php

namespace App\Actions\Organizations;

use App\Actions\Organizations\Concerns\EnsuresOrganizationPrivileges;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteOrganization
{
    use EnsuresOrganizationPrivileges;

    /**
     * Soft delete the organization and revoke any pending invitations.
     * Memberships are retained so the organization can be restored with its
     * membership history intact.
     */
    public function handle(Organization $organization, User $actor): void
    {
        $this->ensureOwner($organization, $actor);

        DB::transaction(function () use ($organization): void {
            $organization = Organization::whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            $organization->invitations()
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $organization->delete();
        });
    }
}
