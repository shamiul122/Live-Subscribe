<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CampaignAudienceType;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\OrganizationRole;
use App\Enums\SocialProvider;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOrganizations;
use Tests\TestCase;

class CampaignAccessTest extends TestCase
{
    use CreatesOrganizations, RefreshDatabase;

    public function test_guests_are_redirected_from_campaign_routes(): void
    {
        $organization = $this->createOrganization(User::factory()->create());

        $this->get(route('organizations.campaigns.index', $organization))->assertRedirect(route('login'));
        $this->get(route('organizations.campaigns.create', $organization))->assertRedirect(route('login'));
        $this->post(route('organizations.campaigns.store', $organization))->assertRedirect(route('login'));
    }

    public function test_member_can_view_the_campaign_index_but_cannot_create(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        $this->addMember($organization, $member, OrganizationRole::Member);

        $this->actingAs($member)
            ->get(route('organizations.campaigns.index', $organization))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/campaigns/Index')
                ->where('permissions.manage', false));

        $this->actingAs($member)
            ->get(route('organizations.campaigns.create', $organization))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_campaign_through_the_http_endpoint(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $admin = User::factory()->create();
        $this->addMember($organization, $admin, OrganizationRole::Admin);
        Contact::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($admin)->post(route('organizations.campaigns.store', $organization), [
            'name' => 'Launch',
            'description' => 'Launch campaign',
            'type' => CampaignType::ProductAnnouncement->value,
            'channel' => SocialProvider::Email->value,
            'connected_account_id' => null,
            'message_content' => 'We launched!',
            'audience_type' => CampaignAudienceType::All->value,
            'audience_config' => [],
        ]);

        $campaign = Campaign::query()->firstOrFail();

        $response->assertRedirect(route('organizations.campaigns.show', [
            'organization' => $organization->slug,
            'campaign' => $campaign->id,
        ]));

        $this->assertSame($organization->id, $campaign->organization_id);
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
    }

    public function test_validation_errors_are_returned_for_invalid_campaign_payloads(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)
            ->from(route('organizations.campaigns.create', $organization))
            ->post(route('organizations.campaigns.store', $organization), [
                'name' => '',
                'type' => 'not-a-type',
                'audience_type' => CampaignAudienceType::Selected->value,
                'audience_config' => ['contact_ids' => []],
            ])
            ->assertSessionHasErrors(['name', 'type', 'audience_config.contact_ids']);
    }

    public function test_a_campaign_from_another_organization_is_not_found(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $otherOrganization = $this->createOrganization($owner, 'Other');
        $campaign = Campaign::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->actingAs($owner)
            ->get(route('organizations.campaigns.show', [
                'organization' => $organization,
                'campaign' => $campaign,
            ]))
            ->assertNotFound();
    }

    public function test_member_cannot_run_lifecycle_actions(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        $this->addMember($organization, $member, OrganizationRole::Member);
        $campaign = Campaign::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($member)
            ->post(route('organizations.campaigns.ready', [
                'organization' => $organization,
                'campaign' => $campaign,
            ]))
            ->assertForbidden();
    }

    public function test_admin_can_mark_a_draft_campaign_ready_through_the_endpoint(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        Contact::factory()->create(['organization_id' => $organization->id]);
        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
            'status' => CampaignStatus::Draft,
            'audience_size' => 1,
        ]);

        $this->actingAs($owner)
            ->post(route('organizations.campaigns.ready', [
                'organization' => $organization,
                'campaign' => $campaign,
            ]))
            ->assertRedirect(route('organizations.campaigns.show', [
                'organization' => $organization->slug,
                'campaign' => $campaign->id,
            ]));

        $this->assertSame(CampaignStatus::Ready, $campaign->refresh()->status);
    }

    public function test_audience_preview_returns_the_organization_scoped_count(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        Contact::factory()->count(3)->create(['organization_id' => $organization->id]);
        Contact::factory()->count(2)->create();

        $this->actingAs($owner)
            ->postJson(route('organizations.campaigns.audience.preview', $organization), [
                'audience_type' => CampaignAudienceType::All->value,
            ])
            ->assertOk()
            ->assertJson(['audience_type' => 'all', 'count' => 3]);
    }

    public function test_recipients_page_lists_organization_scoped_recipients(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = Campaign::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($owner)
            ->get(route('organizations.campaigns.recipients.index', [
                'organization' => $organization,
                'campaign' => $campaign,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('organizations/campaigns/Recipients'));
    }
}
