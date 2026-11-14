<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\CreateOrganization;
use App\Actions\Organizations\DeleteOrganization;
use App\Actions\Organizations\LeaveOrganization;
use App\Actions\Organizations\TransferOrganizationOwnership;
use App\Actions\Organizations\UpdateOrganization;
use App\Enums\OrganizationMembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\StoreOrganizationRequest;
use App\Http\Requests\Organizations\TransferOrganizationOwnershipRequest;
use App\Http\Requests\Organizations\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $memberships = $user->organizationMemberships()
            ->where('status', OrganizationMembershipStatus::Active->value)
            ->get()
            ->keyBy('organization_id');

        $organizations = Organization::query()
            ->whereKey($memberships->keys()->all())
            ->withCount('members')
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $organization): array => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'role' => $memberships->get($organization->id)?->role->value,
                'is_owner' => $organization->owner_id === $user->id,
                'members_count' => $organization->members_count,
            ]);

        return Inertia::render('organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('organizations/Create');
    }

    public function store(
        StoreOrganizationRequest $request,
        CreateOrganization $createOrganization,
    ): RedirectResponse {
        $organization = $createOrganization->handle(
            $request->user(),
            $request->validated('name'),
        );

        $request->session()->put('current_organization_id', $organization->id);

        return redirect()
            ->route('organizations.show', $organization)
            ->with('status', 'Organization created.');
    }

    public function show(Request $request, Organization $organization): Response
    {
        Gate::authorize('view', $organization);

        $user = $request->user();

        $organization->load([
            'owner:id,name,email',
            'memberships' => fn ($query) => $query
                ->with('user:id,name,email')
                ->orderBy('joined_at'),
            'invitations' => fn ($query) => $query
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->with('invitedBy:id,name')
                ->latest(),
        ]);

        $members = $organization->memberships->map(fn ($membership): array => [
            'id' => $membership->id,
            'user' => [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
            ],
            'role' => $membership->role->value,
            'role_label' => $membership->role->label(),
            'status' => $membership->status->value,
            'joined_at' => $membership->joined_at?->toIso8601String(),
            'is_owner' => $membership->user_id === $organization->owner_id,
        ]);

        $invitations = $organization->invitations->map(fn ($invitation): array => [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'role_label' => $invitation->role->label(),
            'invited_by' => $invitation->invitedBy?->name,
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'is_expired' => $invitation->isExpired(),
        ]);

        return Inertia::render('organizations/Show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'owner' => [
                    'id' => $organization->owner->id,
                    'name' => $organization->owner->name,
                    'email' => $organization->owner->email,
                ],
                'created_at' => $organization->created_at?->toIso8601String(),
            ],
            'members' => $members,
            'invitations' => $invitations,
            'permissions' => [
                'update' => $user->can('update', $organization),
                'delete' => $user->can('delete', $organization),
                'invite' => $user->can('inviteMember', $organization),
                'revoke' => $user->can('revokeInvitation', $organization),
                'update_role' => $user->can('updateMemberRole', $organization),
                'remove_member' => $user->can('removeMember', $organization),
                'transfer_ownership' => $user->can('transferOwnership', $organization),
                'leave' => $user->can('leave', $organization),
            ],
            'current_user_role' => $organization->roleFor($user)?->value,
            'invitation_url' => $request->session()->get('invitation_url'),
        ]);
    }

    public function edit(Request $request, Organization $organization): Response
    {
        Gate::authorize('update', $organization);

        return Inertia::render('organizations/Edit', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
        ]);
    }

    public function update(
        UpdateOrganizationRequest $request,
        Organization $organization,
        UpdateOrganization $updateOrganization,
    ): RedirectResponse {
        $updateOrganization->handle(
            $organization,
            $request->user(),
            $request->validated('name'),
        );

        return redirect()
            ->route('organizations.show', $organization)
            ->with('status', 'Organization updated.');
    }

    public function destroy(
        Request $request,
        Organization $organization,
        DeleteOrganization $deleteOrganization,
    ): RedirectResponse {
        Gate::authorize('delete', $organization);

        $deleteOrganization->handle($organization, $request->user());

        if ($request->session()->get('current_organization_id') === $organization->id) {
            $request->session()->forget('current_organization_id');
        }

        return redirect()
            ->route('organizations.index')
            ->with('status', 'Organization deleted.');
    }

    public function transferOwnership(
        TransferOrganizationOwnershipRequest $request,
        Organization $organization,
        TransferOrganizationOwnership $transferOwnership,
    ): RedirectResponse {
        $newOwner = User::where('email', $request->validated('email'))->firstOrFail();

        $transferOwnership->handle($organization, $request->user(), $newOwner);

        return redirect()
            ->route('organizations.show', $organization)
            ->with('status', 'Ownership transferred.');
    }

    public function leave(
        Request $request,
        Organization $organization,
        LeaveOrganization $leaveOrganization,
    ): RedirectResponse {
        Gate::authorize('leave', $organization);

        $leaveOrganization->handle($organization, $request->user());

        if ($request->session()->get('current_organization_id') === $organization->id) {
            $request->session()->forget('current_organization_id');
        }

        return redirect()
            ->route('organizations.index')
            ->with('status', 'You left the organization.');
    }

    public function switch(Request $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('view', $organization);

        $request->session()->put('current_organization_id', $organization->id);

        return back();
    }
}
