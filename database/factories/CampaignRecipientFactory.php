<?php

namespace Database\Factories;

use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignRecipient>
 */
class CampaignRecipientFactory extends Factory
{
    protected $model = CampaignRecipient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'campaign_id' => Campaign::factory(),
            'contact_id' => Contact::factory(),
            'status' => CampaignRecipientStatus::Pending,
            'delivery_status' => null,
            'error_message' => null,
            'queued_at' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'read_at' => null,
            'failed_at' => null,
            'provider_message_id' => null,
            'metadata' => null,
        ];
    }

    public function status(CampaignRecipientStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
