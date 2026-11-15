<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CampaignAudienceType;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\MessageDeliveryStatus;
use App\Enums\SocialProvider;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\ConnectedAccount;
use App\Models\Contact;
use App\Models\User;
use App\Services\Campaigns\CampaignRecipientService;
use App\Services\Campaigns\CampaignService;
use App\Services\Campaigns\CampaignStatisticsService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesOrganizations;
use Tests\TestCase;

class CampaignManagementTest extends TestCase
{
    use CreatesOrganizations, RefreshDatabase;

    public function test_owner_can_create_a_campaign_and_an_audience_snapshot_is_recorded(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        Contact::factory()->count(2)->create(['organization_id' => $organization->id]);

        $campaign = app(CampaignService::class)->create($owner, $organization, $this->payload([
            'name' => 'Spring sale',
        ]));

        $this->assertSame($organization->id, $campaign->organization_id);
        $this->assertSame($owner->id, $campaign->created_by_id);
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
        $this->assertSame(2, $campaign->audience_size);

        $this->assertDatabaseHas('campaign_audience_snapshots', [
            'campaign_id' => $campaign->id,
            'organization_id' => $organization->id,
            'contact_count' => 2,
        ]);
    }

    public function test_selected_contacts_must_belong_to_the_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $foreignContact = Contact::factory()->create();

        $this->expectException(ValidationException::class);

        app(CampaignService::class)->create($owner, $organization, $this->payload([
            'audience_type' => CampaignAudienceType::Selected->value,
            'audience_config' => ['contact_ids' => [$foreignContact->id]],
        ]));
    }

    public function test_selected_audience_is_deduplicated(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $contact = Contact::factory()->create(['organization_id' => $organization->id]);

        $campaign = app(CampaignService::class)->create($owner, $organization, $this->payload([
            'audience_type' => CampaignAudienceType::Selected->value,
            'audience_config' => ['contact_ids' => [$contact->id, $contact->id]],
        ]));

        $this->assertSame(1, $campaign->audience_size);
    }

    public function test_connected_account_must_belong_to_the_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $foreignAccount = ConnectedAccount::factory()->create();

        $this->expectException(ValidationException::class);

        app(CampaignService::class)->create($owner, $organization, $this->payload([
            'connected_account_id' => $foreignAccount->id,
        ]));
    }

    public function test_connected_account_ownership_is_accepted_and_channel_is_derived(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $account = ConnectedAccount::factory()->create([
            'organization_id' => $organization->id,
            'provider' => SocialProvider::WhatsApp,
        ]);

        $campaign = app(CampaignService::class)->create($owner, $organization, $this->payload([
            'channel' => null,
            'connected_account_id' => $account->id,
        ]));

        $this->assertSame($account->id, $campaign->connected_account_id);
        $this->assertSame(SocialProvider::WhatsApp->value, $campaign->channel);
    }

    public function test_campaign_is_scoped_to_the_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $otherOrganization = $this->createOrganization($owner, 'Other');
        $campaign = Campaign::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->expectException(ModelNotFoundException::class);

        app(CampaignService::class)->find($organization, $campaign->id);
    }

    public function test_audience_snapshot_does_not_change_when_contacts_change(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        Contact::factory()->count(2)->create(['organization_id' => $organization->id]);

        $campaign = app(CampaignService::class)->create($owner, $organization, $this->payload());

        // A new contact appears after the campaign was created.
        Contact::factory()->create(['organization_id' => $organization->id]);

        $fresh = app(CampaignService::class)->find($organization, $campaign->id);

        $this->assertSame(2, $fresh->audience_size);
        $this->assertSame(2, $fresh->audienceSnapshot?->contact_count);
    }

    public function test_prepare_recipients_is_idempotent_and_prevents_duplicates(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        Contact::factory()->count(2)->create(['organization_id' => $organization->id]);

        $campaign = app(CampaignService::class)->create($owner, $organization, $this->payload());

        $first = app(CampaignService::class)->prepareRecipients($owner, $organization, $campaign);
        $second = app(CampaignService::class)->prepareRecipients($owner, $organization, $campaign);

        $this->assertSame(2, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['skipped']);
        $this->assertSame(2, CampaignRecipient::query()->where('campaign_id', $campaign->id)->count());

        $this->assertSame(
            2,
            CampaignRecipient::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', CampaignRecipientStatus::Pending->value)
                ->count(),
        );
    }

    public function test_lifecycle_transitions_are_enforced(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        Contact::factory()->create(['organization_id' => $organization->id]);

        $campaign = app(CampaignService::class)->create($owner, $organization, $this->payload());

        $ready = app(CampaignService::class)->markReady($owner, $organization, $campaign);
        $this->assertSame(CampaignStatus::Ready, $ready->status);

        // A ready campaign cannot be paused.
        try {
            app(CampaignService::class)->pause($owner, $organization, $ready);
            $this->fail('Pausing a ready campaign should fail.');
        } catch (ValidationException) {
            // expected
        }

        $cancelled = app(CampaignService::class)->cancel($owner, $organization, $ready);
        $this->assertSame(CampaignStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_cancelled_campaign_cannot_be_edited(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
            'status' => CampaignStatus::Cancelled,
        ]);

        $this->expectException(ValidationException::class);

        app(CampaignService::class)->update($owner, $organization, $campaign, $this->payload(['name' => 'Changed']));
    }

    public function test_paused_campaign_can_be_resumed(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
            'status' => CampaignStatus::Running,
        ]);

        $paused = app(CampaignService::class)->pause($owner, $organization, $campaign);
        $this->assertSame(CampaignStatus::Paused, $paused->status);

        $resumed = app(CampaignService::class)->resume($owner, $organization, $paused);
        $this->assertSame(CampaignStatus::Running, $resumed->status);
    }

    public function test_recipients_cannot_be_prepared_for_a_cancelled_campaign(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
            'status' => CampaignStatus::Cancelled,
        ]);

        $this->expectException(ValidationException::class);

        app(CampaignService::class)->prepareRecipients($owner, $organization, $campaign);
    }

    public function test_campaign_statistics_reflect_recipient_statuses(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = Campaign::factory()->create(['organization_id' => $organization->id]);

        foreach ([
            CampaignRecipientStatus::Delivered,
            CampaignRecipientStatus::Read,
            CampaignRecipientStatus::Failed,
            CampaignRecipientStatus::Pending,
        ] as $status) {
            CampaignRecipient::factory()->create([
                'organization_id' => $organization->id,
                'campaign_id' => $campaign->id,
                'contact_id' => Contact::factory()->create(['organization_id' => $organization->id])->id,
                'status' => $status,
            ]);
        }

        $statistics = app(CampaignStatisticsService::class)->forCampaign($campaign);

        $this->assertSame(4, $statistics['total']);
        $this->assertSame(2, $statistics['delivered']);
        $this->assertSame(1, $statistics['failed']);
        $this->assertSame(1, $statistics['pending']);
        $this->assertSame(50.0, $statistics['delivery_rate']);
    }

    public function test_recipient_status_handling_preserves_delivery_metadata(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $contact = Contact::factory()->create(['organization_id' => $organization->id]);
        $campaign = Campaign::factory()->create(['organization_id' => $organization->id]);

        $recipient = CampaignRecipient::factory()->create([
            'organization_id' => $organization->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => CampaignRecipientStatus::Sent,
            'delivery_status' => MessageDeliveryStatus::Sent,
            'provider_message_id' => 'provider-123',
            'sent_at' => now(),
        ]);

        $array = app(CampaignRecipientService::class)->toArray($recipient);

        $this->assertSame('sent', $array['status']);
        $this->assertSame('Sent', $array['status_label']);
        $this->assertSame('provider-123', $array['provider_message_id']);
        $this->assertNotNull($array['sent_at']);
        $this->assertSame($contact->name, $array['contact']['name']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Campaign',
            'description' => 'A campaign',
            'type' => CampaignType::Promotional->value,
            'channel' => SocialProvider::Email->value,
            'connected_account_id' => null,
            'message_content' => 'Hello world',
            'audience_type' => CampaignAudienceType::All->value,
            'audience_config' => [],
            'scheduled_at' => null,
            'metadata' => null,
        ], $overrides);
    }
}
