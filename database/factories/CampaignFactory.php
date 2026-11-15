<?php

namespace Database\Factories;

use App\Enums\CampaignAudienceType;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\SocialProvider;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'connected_account_id' => null,
            'created_by_id' => User::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'type' => CampaignType::Promotional,
            'channel' => SocialProvider::Email->value,
            'status' => CampaignStatus::Draft,
            'message_content' => fake()->paragraph(),
            'audience_type' => CampaignAudienceType::All,
            'audience_config' => [],
            'audience_size' => 0,
            'scheduled_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'metadata' => ['source' => 'factory'],
        ];
    }

    public function status(CampaignStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function draft(): static
    {
        return $this->status(CampaignStatus::Draft);
    }

    public function ready(): static
    {
        return $this->status(CampaignStatus::Ready);
    }

    public function running(): static
    {
        return $this->status(CampaignStatus::Running);
    }

    public function paused(): static
    {
        return $this->status(CampaignStatus::Paused);
    }

    public function completed(): static
    {
        return $this->status(CampaignStatus::Completed);
    }

    public function cancelled(): static
    {
        return $this->status(CampaignStatus::Cancelled);
    }

    /**
     * @param  array<int, int>  $contactIds
     */
    public function selectedAudience(array $contactIds): static
    {
        return $this->state(fn (): array => [
            'audience_type' => CampaignAudienceType::Selected,
            'audience_config' => ['contact_ids' => $contactIds],
            'audience_size' => count($contactIds),
        ]);
    }
}
