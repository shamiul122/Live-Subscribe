<?php

namespace App\Actions\Organizations;

use App\Actions\Organizations\Concerns\EnsuresOrganizationPrivileges;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\EmailNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteOrganizationMember
{
    use EnsuresOrganizationPrivileges;

    public const DEFAULT_EXPIRY_DAYS = 7;

    /**
     * Create a pending invitation. The raw token is returned on the model via
     * $invitation->plainToken and is never persisted.
     */
    public function handle(
        Organization $organization,
        User $inviter,
        string $email,
        OrganizationRole $role,
        int $expiresInDays = self::DEFAULT_EXPIRY_DAYS,
    ): OrganizationInvitation {
        $this->ensureManager($organization, $inviter);

        $email = EmailNormalizer::normalize($email);

        if ($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages([
                'email' => 'A valid email address is required.',
            ]);
        }

        // Only an owner may grant administrative privileges. This blocks an
        // admin from promoting an invitee above their own effective level.
        if ($role === OrganizationRole::Admin && ! $organization->isOwnedBy($inviter)) {
            throw ValidationException::withMessages([
                'role' => 'Only the organization owner can invite another admin.',
            ]);
        }

        return DB::transaction(function () use ($organization, $inviter, $email, $role, $expiresInDays): OrganizationInvitation {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();

            $existingMember = User::query()
                ->where('email', $email)
                ->whereHas('organizationMemberships', function ($query) use ($organization): void {
                    $query->where('organization_id', $organization->id)
                        ->where('status', OrganizationMembershipStatus::Active->value);
                })
                ->exists();

            if ($existingMember) {
                throw ValidationException::withMessages([
                    'email' => 'This user is already a member of the organization.',
                ]);
            }

            $pendingExists = $organization->invitations()
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->exists();

            if ($pendingExists) {
                throw ValidationException::withMessages([
                    'email' => 'A pending invitation already exists for this email address.',
                ]);
            }

            $plainToken = Str::random(64);

            $invitation = new OrganizationInvitation([
                'organization_id' => $organization->id,
                'invited_by_id' => $inviter->id,
                'email' => $email,
                'expires_at' => now()->addDays($expiresInDays),
            ]);

            $invitation->forceFill([
                'role' => $role,
                'token_hash' => hash('sha256', $plainToken),
            ]);

            $invitation->save();
            $invitation->plainToken = $plainToken;

            return $invitation;
        });
    }
}
