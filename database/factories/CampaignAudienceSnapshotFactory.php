<?php

namespace Database\Factories;

use App\Enums\CampaignAudienceType;
use App\Models\Campaign;
use App\Models\CampaignAudienceSnapshot;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignAudienceSnapshot>
 */
class CampaignAudienceSnapshotFactory extends Factory
{
    protected $model = CampaignAudienceSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'campaign_id' => Campaign::factory(),
            'captured_by_id' => User::factory(),
            'audience_type' => CampaignAudienceType::All,
            'filters' => [],
            'contact_ids' => [],
            'contact_count' => 0,
            'captured_at' => now(),
            'metadata' => null,
        ];
    }
}
