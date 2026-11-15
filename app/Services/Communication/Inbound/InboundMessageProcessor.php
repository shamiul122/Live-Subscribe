<?php

namespace App\Services\Communication\Inbound;

use App\Enums\SocialProvider;
use App\Models\ConnectedAccount;
use App\Models\Contact;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\Communication\CommunicationNotifier;
use App\Services\Communication\CommunicationProviderRegistry;
use App\Services\Communication\Delivery\DeliveryStatusService;
use App\Services\Communication\DTOs\InboundMessageData;
use App\Services\Inbox\ConversationService;
use App\Services\Inbox\MessageService;

/**
 * Turns stored provider webhook payloads into inbox records.
 *
 * Only connected-account identities are trusted; organization ids are derived
 * from that account, never from webhook content.
 */
final class InboundMessageProcessor
{
    public function __construct(
        private readonly CommunicationProviderRegistry $providers,
        private readonly ConversationService $conversations,
        private readonly MessageService $messages,
        private readonly DeliveryStatusService $deliveryStatuses,
        private readonly CommunicationNotifier $notifier,
    ) {
    }

    /**
     * @return array{inbound: int, delivery_statuses: int}
     */
    public function process(WebhookEvent $event): array
    {
        $account = $event->connected_account_id !== null
            ? ConnectedAccount::query()->find($event->connected_account_id)
            : null;

        if ($account === null) {
            throw new \RuntimeException('connected_account_not_found');
        }

        $provider = $this->providers->providerForAccount($account);

        if ($provider === null) {
            throw new \RuntimeException('provider_not_found');
        }

        $payload = is_array($event->payload) ? $event->payload : [];

        if ($payload === []) {
            throw new \RuntimeException('payload_missing');
        }

        $inbound = $provider->normalizeInbound($payload, $account);
        $deliveryStatuses = $provider->normalizeDeliveryStatus($payload, $account);

        $created = 0;

        foreach ($inbound as $data) {
            if ($this->recordInbound($account, $data)) {
                $created++;
            }
        }

        $deliveryUpdated = $this->deliveryStatuses->apply($account, $deliveryStatuses);

        return ['inbound' => $created, 'delivery_statuses' => $deliveryUpdated];
    }

    private function recordInbound(ConnectedAccount $account, InboundMessageData $data): bool
    {
        if ($data->providerMessageId === '') {
            return false;
        }

        $contact = $this->resolveContact($account, $data);
        $preview = $data->body !== null && $data->body !== ''
            ? $data->body
            : $data->messageType->label();

        $conversation = $this->conversations->ensureForProvider(
            $account->organization,
            $account,
            $contact->id,
            $data->providerConversationId,
            null,
            $preview,
        );

        $message = $this->messages->inbound(
            null,
            $account->organization,
            $conversation,
            $data->providerMessageId,
            $data->body,
            $contact->id,
            $data->messageType,
            $data->sentAt,
            array_merge($data->metadata, [
                'inbound_attachments' => $data->attachments,
            ]),
        );

        if (! $message->wasRecentlyCreated) {
            return false;
        }

        if ($data->attachments !== []) {
            $message->metadata = array_merge($message->metadata ?? [], [
                'inbound_attachments' => $data->attachments,
            ]);
            $message->save();
        }

        $this->notifier->notifyInbound($message);

        return true;
    }

    private function resolveContact(ConnectedAccount $account, InboundMessageData $data): Contact
    {
        $metadataKey = match ($account->provider) {
            SocialProvider::Facebook => 'facebook_id',
            SocialProvider::Instagram => 'instagram_id',
            SocialProvider::WhatsApp => 'whatsapp_number',
            SocialProvider::Telegram => 'telegram_chat_id',
            SocialProvider::Email => 'email',
        };

        $identity = match ($account->provider) {
            SocialProvider::Email => $data->senderEmail,
            default => $data->senderId,
        };

        $contact = null;

        if ($identity !== null && $identity !== '') {
            $contact = Contact::query()
                ->where('organization_id', $account->organization_id)
                ->where('metadata->'.$metadataKey, $identity)
                ->first();
        }

        if ($contact === null && $data->senderEmail !== null && $data->senderEmail !== '') {
            $contact = Contact::query()
                ->where('organization_id', $account->organization_id)
                ->where('email', $data->senderEmail)
                ->first();
        }

        if ($contact === null && $data->senderPhone !== null && $data->senderPhone !== '') {
            $contact = Contact::query()
                ->where('organization_id', $account->organization_id)
                ->where('phone', $data->senderPhone)
                ->first();
        }

        if ($contact === null) {
            $contact = Contact::query()->create([
                'organization_id' => $account->organization_id,
                'name' => $this->contactName($data),
                'email' => $data->senderEmail,
                'phone' => $data->senderPhone,
                'metadata' => $identity !== null && $identity !== '' ? [$metadataKey => $identity] : null,
            ]);

            return $contact;
        }

        $metadata = $contact->metadata ?? [];
        $dirty = false;

        if ($identity !== null && $identity !== '' && ($metadata[$metadataKey] ?? null) !== $identity) {
            $metadata[$metadataKey] = $identity;
            $contact->metadata = $metadata;
            $dirty = true;
        }

        if ($contact->email === null && $data->senderEmail !== null) {
            $contact->email = $data->senderEmail;
            $dirty = true;
        }

        if ($contact->phone === null && $data->senderPhone !== null) {
            $contact->phone = $data->senderPhone;
            $dirty = true;
        }

        if ($dirty) {
            $contact->save();
        }

        return $contact;
    }

    private function contactName(InboundMessageData $data): string
    {
        foreach ([$data->senderName, $data->senderEmail, $data->senderPhone, $data->senderId] as $candidate) {
            if ($candidate !== null && trim($candidate) !== '') {
                return \Illuminate\Support\Str::limit(trim($candidate), 255, '');
            }
        }

        return 'Unknown contact';
    }
}
