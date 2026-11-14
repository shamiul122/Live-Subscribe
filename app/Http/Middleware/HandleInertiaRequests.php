<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationMembershipStatus;
use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $organizations = collect();
        $currentOrganization = null;

        if ($user !== null) {
            $memberships = $user->organizationMemberships()
                ->where('status', OrganizationMembershipStatus::Active->value)
                ->get()
                ->keyBy('organization_id');

            $organizations = Organization::query()
                ->whereKey($memberships->keys()->all())
                ->orderBy('name')
                ->get()
                ->map(fn (Organization $organization): array => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'role' => $memberships->get($organization->id)?->role->value,
                    'is_owner' => $organization->owner_id === $user->id,
                ])
                ->values();

            $routeOrganization = $request->route('organization');

            $currentId = $routeOrganization instanceof Organization
                ? $routeOrganization->id
                : (int) $request->session()->get('current_organization_id');

            $currentOrganization = $organizations->firstWhere('id', $currentId)
                ?? $organizations->first();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state')
                || $request->cookie('sidebar_state') === 'true',
            'auth' => [
                'user' => $user,
            ],
            'currentOrganization' => $currentOrganization,
            'organizations' => $organizations,
            'status' => $request->session()->get('status'),
            'invitationUrl' => $request->session()->get('invitation_url'),
        ];
    }
}
