<?php

namespace App\Http\Middleware;

use App\Enums\Feature;
use App\Models\Organization;
use App\Services\Billing\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware that enforces a feature entitlement for the organization
 * resolved from the route.
 *
 * Business features are protected with this instead of inspecting billing
 * tables directly:
 *
 *     Route::get(...)->middleware('organization.entitlement:api_requests');
 *     Route::get(...)->middleware('organization.entitlement:projects,project.create');
 */
class EnsureOrganizationEntitlement
{
    public function __construct(private readonly EntitlementService $entitlements)
    {
    }

    public function handle(Request $request, Closure $next, string $feature, ?string $permission = null): Response
    {
        $organization = $request->route('organization');
        $user = $request->user();

        if (! $organization instanceof Organization) {
            abort(404);
        }

        if ($user === null) {
            abort(401);
        }

        $featureEnum = Feature::tryFrom($feature);

        if ($featureEnum === null) {
            abort(404);
        }

        $decision = $this->entitlements->check($user, $organization, $featureEnum, $permission);

        if (! $decision->isAllowed()) {
            abort($decision->reason->httpStatus(), $decision->reason->label());
        }

        return $next($request);
    }
}
