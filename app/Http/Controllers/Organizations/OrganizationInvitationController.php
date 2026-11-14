<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\InviteOrganizationMember;
use App\Actions\Organizations\RevokeOrganizationInvitation;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\InviteOrganizationMemberRequest;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class OrganizationInvitationController extends Controller
{
    public function store(
        InviteOrganizationMemberRequest $request,
        Organization $organization,
        InviteOrganizationMember $inviteMember,
    ): RedirectResponse {
        $invitation = $inviteMember->handle(
            $organization,
            $request->user(),
            $request->validated('email'),
            OrganizationRole::from($request->validated('role')),
        );

        Notification::route('mail', $invitation->email)
            ->notify(new OrganizationInvitationNotification($invitation, $invitation->plainToken));

        return redirect()
            ->route('organizations.show', $organization)
            ->with('status', 'Invitation sent.')
            ->with('invitation_url', route('invitations.show', ['token' => $invitation->plainToken]));
    }

    public function destroy(
        Request $request,
        Organization $organization,
        OrganizationInvitation $invitation,
        RevokeOrganizationInvitation $revokeInvitation,
    ): RedirectResponse {
        abort_unless($invitation->organization_id === $organization->id, 404);

        Gate::authorize('revokeInvitation', $organization);

        $revokeInvitation->handle($invitation, $request->user());

        return redirect()
            ->route('organizations.show', $organization)
            ->with('status', 'Invitation revoked.');
    }
}
