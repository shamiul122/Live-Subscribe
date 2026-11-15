<?php

namespace App\Http\Controllers\Organizations\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Organizations\Campaigns\Concerns\BuildsCampaignOptions;
use App\Http\Requests\Campaigns\StoreCampaignRequest;
use App\Http\Requests\Campaigns\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\Organization;
use App\Services\Campaigns\CampaignAudienceService;
use App\Services\Campaigns\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    use BuildsCampaignOptions;

    public function __construct(
        private readonly CampaignService $campaigns,
        private readonly CampaignAudienceService $audience,
    ) {}

    public function index(Request $request, Organization $organization): Response
    {
        Gate::authorize('campaign.view', $organization);

        $search = (string) $request->query('search', '');
        $status = $this->statusFromRequest($request);
        $type = $this->typeFromRequest($request);
        $channel = $this->channelFromRequest($request);

        return Inertia::render('organizations/campaigns/Index', [
            'organization' => $this->organizationSummary($organization),
            'campaigns' => $this->campaigns->list($organization, $search, $status, $type, $channel),
            'filters' => [
                'search' => $search,
                'status' => $status?->value,
                'type' => $type?->value,
                'channel' => $channel,
            ],
            'statuses' => $this->statusOptions(),
            'types' => $this->typeOptions(),
            'channels' => $this->channelOptions(),
            'permissions' => [
                'manage' => Gate::allows('campaign.manage', $organization),
            ],
        ]);
    }

    public function create(Organization $organization): Response
    {
        Gate::authorize('campaign.manage', $organization);

        return Inertia::render('organizations/campaigns/Create', $this->formProps($organization));
    }

    public function store(StoreCampaignRequest $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('campaign.manage', $organization);

        $campaign = $this->campaigns->create($request->user(), $organization, $request->validated());

        return redirect()
            ->route('organizations.campaigns.show', [
                'organization' => $organization->slug,
                'campaign' => $campaign->id,
            ])
            ->with('status', 'The campaign was created as a draft.');
    }

    public function show(Organization $organization, Campaign $campaign): Response
    {
        Gate::authorize('campaign.view', $organization);

        $model = $this->campaigns->find($organization, $campaign->id);

        return Inertia::render('organizations/campaigns/Show', [
            'organization' => $this->organizationSummary($organization),
            'campaign' => $this->campaigns->toArray($model, withStatistics: true),
            'permissions' => [
                'manage' => Gate::allows('campaign.manage', $organization),
            ],
        ]);
    }

    public function edit(Organization $organization, Campaign $campaign): Response|RedirectResponse
    {
        Gate::authorize('campaign.manage', $organization);

        $model = $this->campaigns->find($organization, $campaign->id);

        if (! $model->isEditable()) {
            return redirect()
                ->route('organizations.campaigns.show', [
                    'organization' => $organization->slug,
                    'campaign' => $model->id,
                ])
                ->with('status', 'This campaign can no longer be edited.');
        }

        return Inertia::render('organizations/campaigns/Edit', [
            ...$this->formProps($organization),
            'campaign' => $this->campaigns->toArray($model),
        ]);
    }

    public function update(UpdateCampaignRequest $request, Organization $organization, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('campaign.manage', $organization);

        $model = $this->campaigns->find($organization, $campaign->id);

        $this->campaigns->update($request->user(), $organization, $model, $request->validated());

        return redirect()
            ->route('organizations.campaigns.show', [
                'organization' => $organization->slug,
                'campaign' => $model->id,
            ])
            ->with('status', 'The campaign was updated.');
    }

    public function destroy(Organization $organization, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('campaign.manage', $organization);

        $model = $this->campaigns->find($organization, $campaign->id);

        $this->campaigns->delete($organization, $model);

        return redirect()
            ->route('organizations.campaigns.index', ['organization' => $organization->slug])
            ->with('status', 'The campaign was deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(Organization $organization): array
    {
        return [
            'organization' => $this->organizationSummary($organization),
            'types' => $this->typeOptions(),
            'channels' => $this->channelOptions(),
            'audience_types' => $this->audienceTypeOptions(),
            'connected_accounts' => $this->accountOptions($organization),
            'contacts' => $this->audience->availableContacts($organization),
            'permissions' => [
                'manage' => Gate::allows('campaign.manage', $organization),
            ],
        ];
    }

    private function statusFromRequest(Request $request): ?CampaignStatus
    {
        $status = $request->query('status');

        if (! is_string($status) || $status === '') {
            return null;
        }

        return CampaignStatus::tryFrom($status);
    }

    private function typeFromRequest(Request $request): ?CampaignType
    {
        $type = $request->query('type');

        if (! is_string($type) || $type === '') {
            return null;
        }

        return CampaignType::tryFrom($type);
    }

    private function channelFromRequest(Request $request): ?string
    {
        $channel = $request->query('channel');

        if (! is_string($channel) || $channel === '') {
            return null;
        }

        return SocialProvider::tryFrom($channel)?->value;
    }
}
