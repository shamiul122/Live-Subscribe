<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationMembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\EmailNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptOrganizationInvitation
{
    /**
     * Accept an invitation by its raw token. The operation is idempotent with
     * respect to concurrent requests: the invitation row is locked and an
     * already accepted invitation returns the existing membership.
     */
    public function handle(User $user, string $plainToken): OrganizationMembership
    {
        $tokenHash = hash('sha256', $plainToken);

        return DB::transaction(function () use ($user, $tokenHash): OrganizationMembership {
            $invitation = OrganizationInvitation::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw ValidationException::withMessages([
                    'invitation' => 'This invitation is invalid.',
                ]);
            }

            $organization = Organization::withTrashed()
                ->whereKey($invitation->organization_id)
                ->lockForUpdate()
                ->first();

            if ($organization === null || $organization->trashed()) {
                throw ValidationException::withMessages([
                    'invitation' => 'This invitation is no longer valid.',
                ]);
            }

            if ($invitation->isRevoked()) {
                throw ValidationException::withMessages([
                    'invitation' => 'This invitation has been revoked.',
                ]);
            }

            if ($invitation->isAccepted()) {
                $membership = $organization->memberships()
                    ->where('user_id', $user->id)
                    ->first();

                if ($membership !== null) {
                    return $membership;
                }

                throw ValidationException::withMessages([
                    'invitation' => 'This invitation has already been accepted.',
                ]);
            }

            if ($invitation->isExpired()) {
                throw ValidationException::withMessages([
                    'invitation' => 'This invitation has expired.',
                ]);
            }

            if (EmailNormalizer::normalize($user->email) !== $invitation->email) {
                throw ValidationException::withMessages([
                    'invitation' => 'This invitation was sent to a different email address.',
                ]);
            }

            $membership = $organization->memberships()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                $membership = new OrganizationMembership([
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'status' => OrganizationMembershipStatus::Active,
                    'joined_at' => now(),
                ]);

                $membership->forceFill(['role' => $invitation->role]);
                $membership->save();
            } elseif (! $membership->isActive()) {
                $membership->forceFill([
                    'role' => $invitation->role,
                    'status' => OrganizationMembershipStatus::Active,
                    'joined_at' => now(),
                ])->save();
            }

            $invitation->forceFill([
                'accepted_by_id' => $user->id,
                'accepted_at' => now(),
            ])->save();

            return $membership;
        });
    }
}
