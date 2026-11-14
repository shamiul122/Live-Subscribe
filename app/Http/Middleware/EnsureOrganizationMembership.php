<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationMembership
{
    /**
     * Ensure the authenticated user is an active member of the organization
     * resolved from the route.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');
        $user = $request->user();

        if (! $organization instanceof Organization) {
            abort(404);
        }

        if ($user === null) {
            abort(401);
        }

        if (! $organization->isOwnedBy($user) && ! $organization->hasMember($user)) {
            abort(403, 'You are not a member of this organization.');
        }

        return $next($request);
    }
}
