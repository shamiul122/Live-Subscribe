<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\ChangeOrganizationMemberRole;
use App\Actions\Organizations\RemoveOrganizationMember;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpdateOrganizationMemberRoleRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrganizationMemberController extends Controller
{
    public function update(
        UpdateOrganizationMemberRoleRequest $request,
        Organization $organization,
        OrganizationMembership $membership,
        ChangeOrganizationMemberRole $changeRole,
    ): RedirectResponse {
        abort_unless($membership->organization_id === $organization->id, 404);

        $changeRole->handle(
            $organization,
            $membership,
            $request->user(),
            OrganizationRole::from($request->validated('role')),
        );

        return redirect()
            ->route('organizations.show', $organization)
            ->with('status', 'Member role updated.');
    }

    public function destroy(
        Request $request,
        Organization $organization,
        OrganizationMembership $membership,
        RemoveOrganizationMember $removeMember,
    ): RedirectResponse {
        abort_unless($membership->organization_id === $organization->id, 404);

        Gate::authorize('removeMember', $organization);

        $removeMember->handle($organization, $membership, $request->user());

        return redirect()
            ->route('organizations.show', $organization)
            ->with('status', 'Member removed.');
    }
}
