<?php

namespace App\Http\Controllers\Organizations\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Organization;
use App\Services\Campaigns\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Explicit campaign lifecycle transitions.
 *
 * Each action delegates to the campaign service, which validates the current
 * status before moving it. Invalid transitions return a validation error rather
 * than silently changing state.
 */
class CampaignLifecycleController extends Controller
{
    public function __construct(private readonly CampaignService $campaigns) {}

    public function ready(Request $request, Organization $organization, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('campaign.manage', $organization);

        $model = $this->campaigns->find($organization, $campaign->id);
        $this->campaigns->markReady($request->user(), $organization, $model);

        return $this->back($organization, $model, 'The campaign is ready.');
    }

    public function pause(Request $request, Organization $organization, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('campaign.manage', $organization);

        $model = $this->campaigns->find($organization, $campaign->id);
        $this->campaigns->pause($request->user(), $organization, $model);

        return $this->back($organization, $model, 'The campaign was paused.');
    }

    public function resume(Request $request, Organization $organization, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('campaign.manage', $organization);

        $model = $this->campaigns->find($organization, $campaign->id);
        $this->campaigns->resume($request->user(), $organization, $model);

        return $this->back($organization, $model, 'The campaign was resumed.');
    }

    public function cancel(Request $request, Organization $organization, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('campaign.manage', $organization);

        $model = $this->campaigns->find($organization, $campaign->id);
        $this->campaigns->cancel($request->user(), $organization, $model);

        return $this->back($organization, $model, 'The campaign was cancelled.');
    }

    public function prepare(Request $request, Organization $organization, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('campaign.manage', $organization);

        $model = $this->campaigns->find($organization, $campaign->id);
        $result = $this->campaigns->prepareRecipients($request->user(), $organization, $model);

        return $this->back(
            $organization,
            $model,
            "Prepared {$result['created']} recipient(s); {$result['skipped']} already existed. No messages were sent.",
        );
    }

    private function back(Organization $organization, Campaign $campaign, string $status): RedirectResponse
    {
        return redirect()
            ->route('organizations.campaigns.show', [
                'organization' => $organization->slug,
                'campaign' => $campaign->id,
            ])
            ->with('status', $status);
    }
}
