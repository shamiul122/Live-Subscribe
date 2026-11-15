<?php

namespace App\Http\Controllers\Organizations\Campaigns;

use App\Enums\CampaignRecipientStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Organizations\Campaigns\Concerns\BuildsCampaignOptions;
use App\Models\Campaign;
use App\Models\Organization;
use App\Services\Campaigns\CampaignRecipientService;
use App\Services\Campaigns\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CampaignRecipientController extends Controller
{
    use BuildsCampaignOptions;

    public function __construct(
        private readonly CampaignService $campaigns,
        private readonly CampaignRecipientService $recipients,
    ) {}

    public function index(Request $request, Organization $organization, Campaign $campaign): Response
    {
        Gate::authorize('campaign.view', $organization);

        $model = $this->campaigns->find($organization, $campaign->id);
        $status = $this->statusFromRequest($request);

        return Inertia::render('organizations/campaigns/Recipients', [
            'organization' => $this->organizationSummary($organization),
            'campaign' => $this->campaigns->toArray($model, withStatistics: true),
            'recipients' => $this->recipients->list($organization, $model, $status),
            'filters' => [
                'status' => $status?->value,
            ],
            'statuses' => $this->recipientStatusOptions(),
            'permissions' => [
                'manage' => Gate::allows('campaign.manage', $organization),
            ],
        ]);
    }

    private function statusFromRequest(Request $request): ?CampaignRecipientStatus
    {
        $status = $request->query('status');

        if (! is_string($status) || $status === '') {
            return null;
        }

        return CampaignRecipientStatus::tryFrom($status);
    }
}
