<?php

namespace Tests\Feature\Inbox;

use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\ConnectedAccount;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;
use App\Services\Inbox\ConversationEventService;
use App\Services\Inbox\ConversationService;
use App\Services\Inbox\MessageService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesOrganizations;
use Tests\TestCase;

class InboxConversationsTest extends TestCase
{
    use CreatesOrganizations, RefreshDatabase;

    public function test_conversation_can_be_created_for_a_connected_account_of_the_same_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $connectedAccount = ConnectedAccount::factory()->create(['organization_id' => $organization->id]);

        $conversation = app(ConversationService::class)->create(
            $owner,
            $organization,
            $connectedAccount,
            null,
            'Support request',
            'pg-123',
        );

        $this->assertSame($organization->id, $conversation->organization_id);
        $this->assertSame($connectedAccount->id, $conversation->connected_account_id);
        $this->assertSame(ConversationStatus::Open, $conversation->status);
        $this->assertSame(ConversationPriority::Normal, $conversation->priority);
        $this->assertDatabaseHas('conversation_events', [
            'organization_id' => $organization->id,
            'event_type' => ConversationEventService::CONVERSATION_CREATED,
        ]);
    }

    public function test_conversation_creation_rejects_connected_accounts_from_another_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $otherOrganization = $this->createOrganization($owner, 'Other');
        $connectedAccount = ConnectedAccount::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->expectException(ModelNotFoundException::class);

        app(ConversationService::class)->create($owner, $organization, $connectedAccount, null, 'Support request');
    }

    public function test_status_update_records_a_conversation_event(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $conversation = $this->createConversation($organization);

        $updated = app(ConversationService::class)->updateStatus(
            $owner,
            $organization,
            $conversation,
            ConversationStatus::Closed,
        );

        $this->assertSame(ConversationStatus::Closed, $updated->status);

        $this->assertDatabaseHas('conversation_events', [
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'event_type' => ConversationEventService::STATUS_CHANGED,
        ]);
    }

    public function test_priority_update_records_a_conversation_event(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $conversation = $this->createConversation($organization);

        $updated = app(ConversationService::class)->updatePriority(
            $owner,
            $organization,
            $conversation,
            ConversationPriority::Urgent,
        );

        $this->assertSame(ConversationPriority::Urgent, $updated->priority);

        $this->assertDatabaseHas('conversation_events', [
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'event_type' => ConversationEventService::PRIORITY_CHANGED,
        ]);
    }

    public function test_assignment_only_accepts_authorized_organization_members(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $conversation = $this->createConversation($organization);

        $assignee = User::factory()->create();
        $this->addMember($organization, $assignee, \App\Enums\OrganizationRole::Admin);

        $updated = app(ConversationService::class)->assign($owner, $organization, $conversation, $assignee->id);

        $this->assertSame($assignee->id, $updated->assigned_to_id);

        $this->assertDatabaseHas('conversation_events', [
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'event_type' => ConversationEventService::CONVERSATION_ASSIGNED,
        ]);

        $stranger = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(ConversationService::class)->assign($owner, $organization, $conversation, $stranger->id);
    }

    public function test_mark_read_clears_unread_state_and_marks_inbound_messages(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $conversation = $this->createConversation($organization);
        $contact = Contact::factory()->create(['organization_id' => $organization->id]);

        $message = \App\Models\Message::query()->create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'provider_message_id' => 'pm-1',
            'direction' => MessageDirection::Inbound->value,
            'message_type' => MessageType::Text->value,
            'body' => 'Hello',
            'sender_type' => 'contact',
            'sender_id' => $contact->id,
            'sent_at' => now(),
        ]);

        $conversation->unread_count = 2;
        $conversation->save();

        app(MessageService::class)->markRead($owner, $organization, $conversation);

        $conversation->refresh();

        $this->assertSame(0, $conversation->unread_count);
        $this->assertNotNull($conversation->messages()->whereKey($message->id)->first()->read_at);

        $this->assertDatabaseHas('conversation_events', [
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'event_type' => ConversationEventService::CONVERSATION_READ,
        ]);
    }

    public function test_inbound_message_updates_conversation_preview_and_unread_count(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $conversation = $this->createConversation($organization);
        $contact = Contact::factory()->create(['organization_id' => $organization->id]);

        $message = app(MessageService::class)->inbound(
            $owner,
            $organization,
            $conversation,
            'pm-100',
            'Hello, I need help with my order',
            $contact->id,
        );

        $this->assertSame('Hello, I need help with my order', $message->body);
        $this->assertSame(MessageDirection::Inbound, $message->direction);

        $conversation->refresh();

        $this->assertSame(1, $conversation->unread_count);
        $this->assertSame('Hello, I need help with my order', $conversation->last_message_preview);

        // A repeated provider message id must not create a duplicate record.
        $duplicate = app(MessageService::class)->inbound(
            $owner,
            $organization,
            $conversation,
            'pm-100',
            'Hello again',
            $contact->id,
        );

        $this->assertSame($message->id, $duplicate->id);
        $this->assertSame(1, \App\Models\Message::query()->where('provider_message_id', 'pm-100')->count());
    }

    public function test_outbound_message_updates_conversation_activity(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $conversation = $this->createConversation($organization);

        $message = app(MessageService::class)->outbound(
            $owner,
            $organization,
            $conversation,
            null,
            'We are reviewing your request',
            $owner->id,
            MessageType::Text,
        );

        $this->assertSame(MessageDirection::Outbound, $message->direction);

        $conversation->refresh();

        $this->assertSame('We are reviewing your request', $conversation->last_message_preview);
        $this->assertSame(0, $conversation->unread_count);

        $this->assertDatabaseHas('conversation_events', [
            'organization_id' => $organization->id,
            'event_type' => ConversationEventService::MESSAGE_SENT,
        ]);
    }

    private function createConversation(Organization $organization): Conversation
    {
        $connectedAccount = ConnectedAccount::factory()->create(['organization_id' => $organization->id]);
        $contact = Contact::factory()->create(['organization_id' => $organization->id]);

        return Conversation::factory()->create([
            'organization_id' => $organization->id,
            'connected_account_id' => $connectedAccount->id,
            'contact_id' => $contact->id,
            'status' => ConversationStatus::Open,
        ]);
    }
}
