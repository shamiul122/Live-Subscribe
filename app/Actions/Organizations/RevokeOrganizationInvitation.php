<?php

namespace App\Actions\Organizations;

use App\Actions\Organizations\Concerns\EnsuresOrganizationPrivileges;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevokeOrganizationInvitation
{
    use EnsuresOrganizationPrivileges;

    public function handle(OrganizationInvitation $invitation, User $actor): void
    {
        $organization = $invitation->organization;

        $this->ensureManager($organization, $actor);

        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => 'Only pending invitations can be revoked.',
            ]);
        }

        DB::transaction(function () use ($invitation): void {
            $locked = OrganizationInvitation::whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'invitation' => 'Only pending invitations can be revoked.',
                ]);
            }

            $locked->forceFill(['revoked_at' => now()])->save();
        });
    }
}
