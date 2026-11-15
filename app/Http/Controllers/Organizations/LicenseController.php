<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Billing\IssueLicense;
use App\Actions\Billing\RevokeLicense;
use App\Actions\Billing\RotateLicenseKey;
use App\Enums\Feature;
use App\Enums\LicenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\RotateLicenseRequest;
use App\Http\Requests\Billing\StoreLicenseRequest;
use App\Models\License;
use App\Models\Organization;
use App\Services\Billing\BillingPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
    public function __construct(private readonly BillingPresenter $presenter)
    {
    }

    public function index(Request $request, Organization $organization): Response
    {
        Gate::authorize('billing.viewLicenses', $organization);

        $user = $request->user();
        $subscription = $organization->currentSubscription()->first();

        $licenses = $organization->licenses()
            ->latest('id')
            ->get()
            ->map(fn (License $license): array => $this->presenter->licenseSummary($license))
            ->values();

        $limit = $subscription?->entitlementLimit(Feature::Licenses);
        $used = $organization->licenses()
            ->where('status', LicenseStatus::Active->value)
            ->count();

        return Inertia::render('organizations/licenses/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'licenses' => $licenses,
            'capacity' => $this->presenter->featureLine(Feature::Licenses, $limit, $used),
            'has_subscription' => $subscription !== null,
            'plain_key' => $request->session()->get('license_key'),
            'permissions' => [
                'manage' => $user->can('billing.manageLicenses', $organization),
            ],
        ]);
    }

    public function store(StoreLicenseRequest $request, Organization $organization, IssueLicense $issue): RedirectResponse
    {
        $license = $issue->handle($organization, $request->user(), $request->validated('label'));

        return redirect()
            ->route('organizations.licenses.index', $organization)
            ->with('status', 'License issued. Copy the key now: it will not be shown again.')
            ->with('license_key', $license->plainKey);
    }

    public function rotate(RotateLicenseRequest $request, Organization $organization, License $license, RotateLicenseKey $rotate): RedirectResponse
    {
        $rotated = $rotate->handle($license, $request->user());

        return redirect()
            ->route('organizations.licenses.index', $organization)
            ->with('status', 'License key rotated. The previous key no longer works.')
            ->with('license_key', $rotated->plainKey);
    }

    public function destroy(Request $request, Organization $organization, License $license, RevokeLicense $revoke): RedirectResponse
    {
        Gate::authorize('billing.manageLicenses', $organization);

        abort_unless($license->organization_id === $organization->id, 404);

        $revoke->handle($license, $request->user());

        return redirect()
            ->route('organizations.licenses.index', $organization)
            ->with('status', 'License revoked.');
    }
}
