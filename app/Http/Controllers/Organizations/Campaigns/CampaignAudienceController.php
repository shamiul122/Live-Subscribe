<?php

namespace App\Http\Controllers\Organizations\Campaigns;

use App\Enums\CampaignAudienceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaigns\PreviewCampaignAudienceRequest;
use App\Models\Organization;
use App\Services\Campaigns\CampaignAudienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Server-authoritative audience estimation.
 *
 * The create and edit screens call this endpoint to show an estimated audience
 * size before a campaign is executed. The estimate is never trusted for
 * storage: the campaign service rebuilds and validates the snapshot on save.
 */
class CampaignAudienceController extends Controller
{
    public function __construct(private readonly CampaignAudienceService $audience) {}

    public function preview(PreviewCampaignAudienceRequest $request, Organization $organization): JsonResponse
    {
        Gate::authorize('campaign.manage', $organization);

        $type = CampaignAudienceType::from((string) $request->validated('audience_type'));
        $config = $request->validated('audience_config');

        return response()->json(
            $this->audience->preview($organization, $type, is_array($config) ? $config : []),
        );
    }
}
