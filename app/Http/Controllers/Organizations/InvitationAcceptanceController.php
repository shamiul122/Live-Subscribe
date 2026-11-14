<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\AcceptOrganizationInvitation;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Support\EmailNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvitationAcceptanceController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->findInvitation($token);

        abort_if($invitation === null, 404);

        $organization = Organization::withTrashed()
            ->whereKey($invitation->organization_id)
            ->first();

        $reason = $this->invalidReason($request, $invitation, $organization);

        return Inertia::render('organizations/Invitations/Show', [
            'token' => $token,
            'valid' => $reason === null,
            'reason' => $reason,
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                'organization_name' => $organization?->name,
                'invited_by' => $invitation->invitedBy?->name,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        ]);
    }

    public function store(
        Request $request,
        string $token,
        AcceptOrganizationInvitation $acceptInvitation,
    ): RedirectResponse {
        $invitation = $this->findInvitation($token);

        abort_if($invitation === null, 404);

        $membership = $acceptInvitation->handle($request->user(), $token);

        $organization = Organization::findOrFail($membership->organization_id);

        $request->session()->put('current_organization_id', $organization->id);

        return redirect()
            ->route('organizations.show', $organization)
            ->with('status', 'Invitation accepted.');
    }

    private function findInvitation(string $token): ?OrganizationInvitation
    {
        return OrganizationInvitation::query()
            ->with('invitedBy:id,name')
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    private function invalidReason(
        Request $request,
        OrganizationInvitation $invitation,
        ?Organization $organization,
    ): ?string {
        if ($organization === null || $organization->trashed()) {
            return 'This invitation is no longer valid.';
        }

        if ($invitation->isRevoked()) {
            return 'This invitation has been revoked.';
        }

        if ($invitation->isAccepted()) {
            return 'This invitation has already been accepted.';
        }

        if ($invitation->isExpired()) {
            return 'This invitation has expired.';
        }

        if (EmailNormalizer::normalize($request->user()->email) !== $invitation->email) {
            return 'This invitation was sent to a different email address.';
        }

        return null;
    }
}
