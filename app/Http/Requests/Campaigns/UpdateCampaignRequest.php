<?php

namespace App\Http\Requests\Campaigns;

/**
 * Editing a campaign uses the same validated shape as creating one. Whether the
 * campaign may be edited at all is decided by the campaign service, which knows
 * the campaign status.
 */
class UpdateCampaignRequest extends StoreCampaignRequest {}
