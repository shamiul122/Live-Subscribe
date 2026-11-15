<?php

namespace Tests\Feature\Templates;

use App\Enums\MessageTemplateCategory;
use App\Enums\MessageTemplateStatus;
use App\Enums\OrganizationRole;
use App\Enums\SocialProvider;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOrganizations;
use Tests\TestCase;

class MessageTemplateAccessTest extends TestCase
{
    use CreatesOrganizations, RefreshDatabase;

    public function test_guests_are_redirected_from_template_routes(): void
    {
        $organization = $this->createOrganization(User::factory()->create());

        $this->get(route('organizations.message-templates.index', $organization))
            ->assertRedirect(route('login'));

        $this->get(route('organizations.message-templates.create', $organization))
            ->assertRedirect(route('login'));

        $this->post(route('organizations.message-templates.store', $organization))
            ->assertRedirect(route('login'));
    }

    public function test_member_can_view_the_index_but_cannot_create(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        $this->addMember($organization, $member, OrganizationRole::Member);

        $this->actingAs($member)
            ->get(route('organizations.message-templates.index', $organization))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/message-templates/Index')
                ->where('permissions.manage', false));

        $this->actingAs($member)
            ->get(route('organizations.message-templates.create', $organization))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_template_through_the_endpoint(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $admin = User::factory()->create();
        $this->addMember($organization, $admin, OrganizationRole::Admin);

        $response = $this->actingAs($admin)->post(
            route('organizations.message-templates.store', $organization),
            [
                'name' => 'Order update',
                'description' => 'Shipping notice',
                'channel' => SocialProvider::Email->value,
                'category' => MessageTemplateCategory::OrderUpdate->value,
                'status' => MessageTemplateStatus::Active->value,
                'subject' => 'Your order shipped',
                'content' => 'Hi {{customer_name}}, order {{order_number}} is on its way.',
            ],
        );

        $template = MessageTemplate::query()->firstOrFail();

        $response->assertRedirect(route('organizations.message-templates.show', [
            'organization' => $organization->slug,
            'message_template' => $template->id,
        ]));

        $this->assertSame($organization->id, $template->organization_id);
        $this->assertSame(['customer_name', 'order_number'], $template->variables);
    }

    public function test_unknown_variables_are_rejected_on_store(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)
            ->from(route('organizations.message-templates.create', $organization))
            ->post(route('organizations.message-templates.store', $organization), [
                'name' => 'Bad template',
                'category' => MessageTemplateCategory::Custom->value,
                'status' => MessageTemplateStatus::Draft->value,
                'content' => 'Hello {{not_a_real_variable}}',
            ])
            ->assertSessionHasErrors('content');
    }

    public function test_a_template_from_another_organization_is_not_found(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $otherOrganization = $this->createOrganization($owner, 'Other');
        $template = MessageTemplate::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->actingAs($owner)
            ->get(route('organizations.message-templates.show', [
                'organization' => $organization,
                'message_template' => $template,
            ]))
            ->assertNotFound();
    }

    public function test_member_cannot_archive_a_template(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        $this->addMember($organization, $member, OrganizationRole::Member);
        $template = MessageTemplate::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($member)
            ->post(route('organizations.message-templates.archive', [
                'organization' => $organization,
                'message_template' => $template,
            ]))
            ->assertForbidden();
    }

    public function test_owner_can_archive_and_restore_a_template(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $template = MessageTemplate::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($owner)
            ->post(route('organizations.message-templates.archive', [
                'organization' => $organization,
                'message_template' => $template,
            ]))
            ->assertRedirect();

        $this->assertSame(MessageTemplateStatus::Archived, $template->refresh()->status);

        $this->actingAs($owner)
            ->post(route('organizations.message-templates.restore', [
                'organization' => $organization,
                'message_template' => $template,
            ]))
            ->assertRedirect();

        $this->assertSame(MessageTemplateStatus::Draft, $template->refresh()->status);
    }

    public function test_preview_page_renders_for_a_member(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        $this->addMember($organization, $member, OrganizationRole::Member);
        $template = MessageTemplate::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($member)
            ->get(route('organizations.message-templates.preview.show', [
                'organization' => $organization,
                'message_template' => $template,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/message-templates/Preview'));
    }

    public function test_preview_render_endpoint_returns_rendered_content(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)
            ->postJson(route('organizations.message-templates.preview.render', $organization), [
                'subject' => 'Hello {{customer_name}}',
                'content' => 'Your order {{order_number}} is ready.',
            ])
            ->assertOk()
            ->assertJson([
                'subject' => 'Hello Jane Doe',
                'content' => 'Your order ORD-1042 is ready.',
                'used_variables' => ['order_number'],
                'unknown_variables' => [],
            ]);
    }

    public function test_preview_render_endpoint_rejects_unknown_variables(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)
            ->postJson(route('organizations.message-templates.preview.render', $organization), [
                'content' => 'Hello {{nope}}',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('content');
    }

    public function test_campaign_form_exposes_active_templates(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        MessageTemplate::factory()->active()->create(['organization_id' => $organization->id]);
        MessageTemplate::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($owner)
            ->get(route('organizations.campaigns.create', $organization))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/campaigns/Create')
                ->has('templates', 1));
    }
}
