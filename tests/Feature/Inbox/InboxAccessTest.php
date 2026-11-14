<?php

namespace Tests\Feature\Inbox;

use App\Models\ConnectedAccount;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;
use App\Services\Inbox\ConversationService;
use App\Services\Inbox\ContactService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOrganizations;
use Tests\TestCase;

class InboxAccessTest extends TestCase
{
    use CreatesOrganizations, RefreshDatabase;

    public function test_guests_are_redirected_from_inbox_routes(): void
    {
        $organization = $this->createOrganization(User::factory()->create());

        $this->get(route('organizations.inbox.index', $organization))->assertRedirect(route('login'));
        $this->get(route('organizations.inbox.contacts.index', $organization))->assertRedirect(route('login'));
        $this->post(route('organizations.inbox.conversations.store', $organization))->assertRedirect(route('login'));
    }

    public function test_organization_member_can_access_the_inbox_index(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)
            ->get(route('organizations.inbox.index', $organization))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('organizations/inbox/Index'));
    }

    public function test_member_cannot_access_an_organization_outside_their_membership(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        $this->addMember($organization, $member, \App\Enums\OrganizationRole::Member);

        $this->actingAs($member)
            ->get(route('organizations.inbox.index', $this->createOrganization($owner, 'Other')))
            ->assertForbidden();
    }

    public function test_member_can_view_their_own_conversation(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $conversation = $this->createConversation($organization);

        $this->actingAs($owner)
            ->get(route('organizations.inbox.show', ['organization' => $organization, 'conversation' => $conversation]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/inbox/Conversation')
                ->where('conversation.id', $conversation->id));
    }

    public function test_conversation_is_scoped_to_the_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $otherOrganization = $this->createOrganization($owner, 'Other');
        $conversation = $this->createConversation($otherOrganization);

        $this->expectException(ModelNotFoundException::class);

        app(ConversationService::class)->find($organization, $conversation->id);
    }

    public function test_contacts_are_scoped_to_the_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $otherOrganization = $this->createOrganization($owner, 'Other');
        $contact = Contact::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->expectException(ModelNotFoundException::class);

        app(ContactService::class)->find($organization, $contact->id);
    }

    public function test_member_can_access_the_inbox_but_cannot_manage_conversations(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        $this->addMember($organization, $member, \App\Enums\OrganizationRole::Member);

        $this->actingAs($member)
            ->get(route('organizations.inbox.index', $organization))
            ->assertOk();

        $this->actingAs($member)
            ->post(route('organizations.inbox.conversations.store', $organization), [])
            ->assertForbidden();
    }

    public function test_admin_can_access_the_inbox_conversation_show(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $admin = User::factory()->create();
        $this->addMember($organization, $admin, \App\Enums\OrganizationRole::Admin);
        $conversation = $this->createConversation($organization);

        $this->actingAs($admin)
            ->get(route('organizations.inbox.show', ['organization' => $organization, 'conversation' => $conversation]))
            ->assertOk();
    }

    private function createConversation(Organization $organization): Conversation
    {
        $connectedAccount = ConnectedAccount::factory()->create(['organization_id' => $organization->id]);
        $contact = Contact::factory()->create(['organization_id' => $organization->id]);

        return Conversation::factory()->create([
            'organization_id' => $organization->id,
            'connected_account_id' => $connectedAccount->id,
            'contact_id' => $contact->id,
        ]);
    }
}
