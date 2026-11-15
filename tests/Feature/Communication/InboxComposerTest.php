<?php

namespace Tests\Feature\Communication;

use App\Enums\MessageDeliveryStatus;
use App\Enums\OrganizationRole;
use App\Enums\SocialProvider;
use App\Models\Attachment;
use App\Models\ConnectedAccount;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\DeliveryAttempt;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCommunicationContext;
use Tests\TestCase;

class InboxComposerTest extends TestCase
{
    use CreatesCommunicationContext, RefreshDatabase;

    public function test_owner_can_send_a_text_message_from_the_composer(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);
        [$owner, $organization, $account, , $conversation] = $this->communicationContext(SocialProvider::Facebook);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['message_id' => 'mid.composer.1'], 200),
        ]);

        $this->actingAs($owner)
            ->post(route('organizations.inbox.messages.store', $organization), [
                'conversation_id' => $conversation->id,
                'connected_account_id' => $account->id,
                'body' => 'Hello from the composer',
            ])
            ->assertRedirect(route('organizations.inbox.show', [
                'organization' => $organization->slug,
                'conversation' => $conversation->id,
            ]))
            ->assertSessionHasNoErrors();

        $message = Message::query()->sole();

        $this->assertSame('Hello from the composer', $message->body);
        $this->assertSame(MessageDeliveryStatus::Sent, $message->delivery_status);
        $this->assertSame('mid.composer.1', $message->provider_message_id);
        $this->assertSame($owner->id, $message->sender_id);
    }

    public function test_whatsapp_composer_uploads_and_links_an_attachment(): void
    {
        Storage::fake('local');
        config(['communication.attachments.disk' => 'local']);

        $this->configureCommunicationProvider(SocialProvider::WhatsApp);
        [$owner, $organization, $account, , $conversation] = $this->communicationContext(SocialProvider::WhatsApp);

        Http::fakeSequence('graph.facebook.com/*')
            ->push(['id' => 'media-1'], 200)
            ->push(['messages' => [['id' => 'wamid.media.1']]], 200);

        $file = UploadedFile::fake()->create('report.pdf', 12, 'application/pdf');

        $this->actingAs($owner)
            ->post(route('organizations.inbox.messages.store', $organization), [
                'conversation_id' => $conversation->id,
                'connected_account_id' => $account->id,
                'body' => 'Here is the report',
                'attachments' => [$file],
            ])
            ->assertSessionHasNoErrors();

        $message = Message::query()->sole();
        $attachment = Attachment::query()->sole();

        $this->assertSame($message->id, $attachment->message_id);
        $this->assertSame('report.pdf', $attachment->original_name);
        $this->assertSame($organization->id, $attachment->organization_id);
        $this->assertSame(MessageDeliveryStatus::Sent, $message->delivery_status);
        $this->assertSame('wamid.media.1', $message->provider_message_id);
        Storage::disk('local')->assertExists($attachment->storage_path);

        Http::assertSentCount(2);
    }

    public function test_unsupported_attachment_capability_rolls_back_the_uploaded_file(): void
    {
        Storage::fake('local');
        config(['communication.attachments.disk' => 'local']);

        $this->configureCommunicationProvider(SocialProvider::Facebook);
        [$owner, $organization, $account, , $conversation] = $this->communicationContext(SocialProvider::Facebook);

        Http::fake();

        $file = UploadedFile::fake()->create('note.txt', 4, 'text/plain');

        $this->actingAs($owner)
            ->post(route('organizations.inbox.messages.store', $organization), [
                'conversation_id' => $conversation->id,
                'connected_account_id' => $account->id,
                'attachments' => [$file],
            ])
            ->assertSessionHasErrors('attachments');

        $this->assertSame(0, Message::query()->count());
        $this->assertSame(0, Attachment::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        Http::assertNothingSent();
    }

    public function test_unconfigured_provider_is_reported_in_the_composer_and_rejects_sending(): void
    {
        config([
            'social.facebook.client_id' => null,
            'social.facebook.client_secret' => null,
            'social.facebook.app_secret' => null,
        ]);

        [$owner, $organization, $account, , $conversation] = $this->communicationContext(SocialProvider::Facebook);

        $this->actingAs($owner)
            ->get(route('organizations.inbox.show', [
                'organization' => $organization,
                'conversation' => $conversation,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/inbox/Conversation')
                ->where('communication.configured', false)
                ->where('communication.usable', false)
                ->where('communication.capabilities.outbound_text', true)
                ->where('communication.capabilities.inbound_webhooks', true));

        Http::fake();

        $this->actingAs($owner)
            ->post(route('organizations.inbox.messages.store', $organization), [
                'conversation_id' => $conversation->id,
                'connected_account_id' => $account->id,
                'body' => 'This must not be stored',
            ])
            ->assertSessionHasErrors('message');

        $this->assertSame(0, Message::query()->count());
        Http::assertNothingSent();
    }

    public function test_members_cannot_send_from_the_composer(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);
        [, $organization, $account, , $conversation] = $this->communicationContext(SocialProvider::Facebook);

        $member = User::factory()->create();
        $this->addMember($organization, $member, OrganizationRole::Member);

        Http::fake();

        $this->actingAs($member)
            ->post(route('organizations.inbox.messages.store', $organization), [
                'conversation_id' => $conversation->id,
                'connected_account_id' => $account->id,
                'body' => 'Member message',
            ])
            ->assertForbidden();

        $this->assertSame(0, Message::query()->count());
        Http::assertNothingSent();
    }

    public function test_failed_message_can_be_retried_from_the_retry_route(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);
        [$owner, $organization, $account, , $conversation] = $this->communicationContext(SocialProvider::Facebook);

        $message = Message::factory()->failed()->create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'provider_message_id' => null,
            'body' => 'Retry from the UI',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['message_id' => 'mid.retried.1'], 200),
        ]);

        $this->actingAs($owner)
            ->post(route('organizations.inbox.messages.retry', [
                'organization' => $organization,
                'message' => $message,
            ]))
            ->assertRedirect(route('organizations.inbox.show', [
                'organization' => $organization->slug,
                'conversation' => $conversation->id,
            ]))
            ->assertSessionHasNoErrors();

        $message->refresh();

        $this->assertSame(MessageDeliveryStatus::Sent, $message->delivery_status);
        $this->assertNull($message->failure_reason);
        $this->assertSame('mid.retried.1', $message->provider_message_id);

        $attempt = DeliveryAttempt::query()->where('message_id', $message->id)->sole();
        $this->assertSame($account->id, $attempt->connected_account_id);
    }

    public function test_retry_cannot_target_a_message_from_another_organization(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);
        [$owner, $organization] = $this->communicationContext(SocialProvider::Facebook);

        $otherOrganization = $this->createOrganization($owner, 'Other');
        $otherAccount = ConnectedAccount::factory()->create([
            'organization_id' => $otherOrganization->id,
            'provider' => SocialProvider::Facebook,
            'provider_account_id' => 'page-2',
        ]);
        $otherContact = Contact::factory()->create(['organization_id' => $otherOrganization->id]);
        $otherConversation = Conversation::factory()->create([
            'organization_id' => $otherOrganization->id,
            'connected_account_id' => $otherAccount->id,
            'contact_id' => $otherContact->id,
            'provider' => SocialProvider::Facebook,
        ]);
        $otherMessage = Message::factory()->failed()->create([
            'organization_id' => $otherOrganization->id,
            'conversation_id' => $otherConversation->id,
            'provider_message_id' => null,
        ]);

        Http::fake();

        $this->actingAs($owner)
            ->post(route('organizations.inbox.messages.retry', [
                'organization' => $organization,
                'message' => $otherMessage,
            ]))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_attachment_download_is_limited_to_the_organization(): void
    {
        Storage::fake('local');

        [$owner, $organization] = $this->communicationContext(SocialProvider::Facebook);
        $path = 'attachments/'.$organization->id.'/sample.txt';
        Storage::disk('local')->put($path, 'attachment contents');

        $attachment = Attachment::factory()->create([
            'organization_id' => $organization->id,
            'disk' => 'local',
            'storage_path' => $path,
            'original_name' => 'sample.txt',
        ]);

        $this->actingAs($owner)
            ->get(route('organizations.inbox.attachments.download', [
                'organization' => $organization,
                'attachment' => $attachment,
            ]))
            ->assertOk()
            ->assertDownload('sample.txt');

        $otherOrganization = $this->createOrganization($owner, 'Other');
        $otherPath = 'attachments/'.$otherOrganization->id.'/other.txt';
        Storage::disk('local')->put($otherPath, 'other');

        $otherAttachment = Attachment::factory()->create([
            'organization_id' => $otherOrganization->id,
            'disk' => 'local',
            'storage_path' => $otherPath,
        ]);

        $this->actingAs($owner)
            ->get(route('organizations.inbox.attachments.download', [
                'organization' => $organization,
                'attachment' => $otherAttachment,
            ]))
            ->assertNotFound();

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('organizations.inbox.attachments.download', [
                'organization' => $organization,
                'attachment' => $attachment,
            ]))
            ->assertForbidden();
    }

    public function test_member_can_view_the_conversation_but_sees_read_only_capabilities(): void
    {
        $this->configureCommunicationProvider(SocialProvider::Facebook);
        [, $organization, , , $conversation] = $this->communicationContext(SocialProvider::Facebook);

        $member = User::factory()->create();
        $this->addMember($organization, $member, OrganizationRole::Member);

        $this->actingAs($member)
            ->get(route('organizations.inbox.show', [
                'organization' => $organization,
                'conversation' => $conversation,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/inbox/Conversation')
                ->where('permissions.manage', false));
    }

}
