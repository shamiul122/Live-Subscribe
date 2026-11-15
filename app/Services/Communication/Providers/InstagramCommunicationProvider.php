<?php

namespace App\Services\Communication\Providers;

use App\Enums\CommunicationCapability;
use App\Enums\CommunicationErrorCode;
use App\Enums\MessageDeliveryStatus;
use App\Enums\SocialProvider;
use App\Models\ConnectedAccount;
use App\Services\Communication\AbstractCommunicationProvider;
use App\Services\Communication\DTOs\DeliveryStatusData;
use App\Services\Communication\DTOs\InboundMessageData;
use App\Services\Communication\DTOs\ProviderAccountStatus;
use App\Services\Communication\DTOs\ProviderSendResult;
use App\Services\Communication\DTOs\WebhookVerificationResult;

/**
 * Instagram Direct adapter for Instagram Professional accounts.
 *
 * Instagram messaging uses the Instagram Graph API through a Facebook app. The
 * account needs `instagram_basic`, `instagram_manage_messages` and a linked
 * Facebook Page. Outbound attachments are intentionally disabled.
 */
final class InstagramCommunicationProvider extends AbstractCommunicationProvider
{
    public function identifier(): string
    {
        return 'instagram';
    }

    public function provider(): SocialProvider
    {
        return SocialProvider::Instagram;
    }

    public function isConfigured(): bool
    {
        $clientId = config('social.instagram.client_id') ?: config('social.facebook.client_id');
        $clientSecret = config('social.instagram.client_secret') ?: config('social.facebook.client_secret');

        return (bool) $clientId && (bool) $clientSecret;
    }

    public function accountStatus(ConnectedAccount $account): ProviderAccountStatus
    {
        if (! $this->isConfigured()) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::ProviderNotConfigured,
                'The Instagram app is not configured.',
                configured: false,
            );
        }

        if ($account->provider !== SocialProvider::Instagram) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::AccountNotFound,
                'The connected account is not an Instagram professional account.',
            );
        }

        if ($account->status !== \App\Enums\ConnectedAccountStatus::Active) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::InvalidAccountCredentials,
                'The connected Instagram account is not active.',
            );
        }

        if ($account->token_expires_at !== null && $account->token_expires_at->isPast()) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::InvalidAccountCredentials,
                'The Instagram access token has expired.',
            );
        }

        if ((string) $account->provider_account_id === '') {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::InvalidAccountCredentials,
                'The connected Instagram account has no professional account identifier.',
            );
        }

        if (! is_string($account->access_token) || $account->access_token === '') {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::InvalidAccountCredentials,
                'No Instagram access token is available for this account.',
            );
        }

        return ProviderAccountStatus::ready();
    }

    public function supports(CommunicationCapability $capability): bool
    {
        return match ($capability) {
            CommunicationCapability::OutboundText => true,
            CommunicationCapability::DeliveryStatus => true,
            CommunicationCapability::InboundWebhooks => true,
            CommunicationCapability::Attachments => false,
        };
    }

    public function capabilityReason(CommunicationCapability $capability): ?string
    {
        return match ($capability) {
            CommunicationCapability::Attachments => 'Outbound Instagram attachments require a publicly reachable media URL and are not enabled in this foundation.',
            default => parent::capabilityReason($capability),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $context
     */
    public function verifyWebhook(string $method, array $payload, array $headers, array $context = []): WebhookVerificationResult
    {
        if (strtolower($method) === 'get') {
            $token = (string) (config('social.instagram.webhook_verify_token') ?: config('social.facebook.webhook_verify_token'));

            return $this->verifyHubChallenge($payload, $token);
        }

        $secret = (string) (config('social.instagram.app_secret') ?: config('social.facebook.app_secret') ?: config('social.facebook.client_secret'));

        return $this->verifyMetaSignature($secret, $headers, $context);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $context
     */
    public function resolveAccount(array $payload, array $headers, array $context = []): ?ConnectedAccount
    {
        $accountId = $this->metaEntryId($payload);

        if ($accountId === null || $accountId === '') {
            return null;
        }

        return ConnectedAccount::query()
            ->where('provider', SocialProvider::Instagram->value)
            ->where('provider_account_id', $accountId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Instagram delivers messaging events in either the Messenger `messaging`
     * shape or the `changes[].value` shape. Both are normalized here.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<int, InboundMessageData>
     */
    public function normalizeInbound(array $payload, ConnectedAccount $account, array $context = []): array
    {
        $messages = $this->normalizeMetaMessaging($payload);

        if ($messages !== []) {
            return $messages;
        }

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (! is_array($change) || ($change['field'] ?? null) !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? null;

                if (! is_array($value)) {
                    continue;
                }

                $message = $value['message'] ?? null;

                if (! is_array($message)) {
                    continue;
                }

                $senderId = isset($value['sender']['id']) ? (string) $value['sender']['id'] : null;
                $text = isset($message['text']) && is_string($message['text']) ? $message['text'] : null;

                $messages[] = new InboundMessageData(
                    providerMessageId: (string) ($message['mid'] ?? ''),
                    body: $text,
                    senderId: $senderId,
                    senderName: null,
                    providerConversationId: $senderId,
                    messageType: \App\Enums\MessageType::Text,
                    sentAt: isset($value['timestamp']) ? now()->setTimestamp((int) floor(((int) $value['timestamp']) / 1000)) : now(),
                    metadata: ['provider' => 'instagram', 'source' => 'webhook'],
                );
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendText(ConnectedAccount $account, string $recipient, string $body, array $context = []): ProviderSendResult
    {
        if (trim($recipient) === '') {
            return ProviderSendResult::failure(CommunicationErrorCode::InvalidRecipient, 'No Instagram recipient is available.');
        }

        $status = $this->accountStatus($account);

        if (! $status->usable) {
            return ProviderSendResult::failure($status->errorCode ?? CommunicationErrorCode::InvalidAccountCredentials, $status->errorMessage);
        }

        $base = rtrim((string) config('social.instagram.api_base_url', 'https://graph.facebook.com/v20.0'), '/');
        $accountId = rawurlencode((string) $account->provider_account_id);

        try {
            $response = $this->httpClient()
                ->withToken((string) $account->access_token)
                ->post("{$base}/{$accountId}/messages", [
                    'recipient' => ['id' => $recipient],
                    'message' => ['text' => $body],
                ]);

            $data = $response->json();
            $data = is_array($data) ? $data : [];

            if (! $response->successful()) {
                return $this->rejected($response, $data);
            }

            $messageId = $data['message_id'] ?? null;

            if (! is_string($messageId) || $messageId === '') {
                return ProviderSendResult::failure(
                    CommunicationErrorCode::ProviderError,
                    'Instagram accepted the request without returning a message id.',
                );
            }

            return ProviderSendResult::accepted($messageId, ['http_status' => $response->status()]);
        } catch (\Throwable) {
            return $this->networkFailure(new \RuntimeException('instagram_http_failed'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function providerEventId(array $payload): ?string
    {
        $message = $payload['entry'][0]['messaging'][0]['message']['mid'] ?? null;

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return parent::providerEventId($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function eventType(array $payload): string
    {
        $hasMessage = false;
        $hasStatus = false;

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ((array) ($entry['messaging'] ?? []) as $event) {
                if (! is_array($event)) {
                    continue;
                }

                if (isset($event['message']) && ($event['message']['is_echo'] ?? false) !== true) {
                    $hasMessage = true;
                }

                if (isset($event['delivery']) || isset($event['read'])) {
                    $hasStatus = true;
                }
            }
        }

        return $hasMessage ? 'inbound_message' : ($hasStatus ? 'delivery_status' : 'webhook_event');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, DeliveryStatusData>
     */
    public function normalizeDeliveryStatus(array $payload, ConnectedAccount $account): array
    {
        $statuses = [];

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ((array) ($entry['messaging'] ?? []) as $event) {
                if (! is_array($event)) {
                    continue;
                }

                if (isset($event['delivery']['mids']) && is_array($event['delivery']['mids'])) {
                    foreach ($event['delivery']['mids'] as $mid) {
                        if (is_string($mid) && $mid !== '') {
                            $statuses[] = new DeliveryStatusData(
                                providerMessageId: $mid,
                                status: MessageDeliveryStatus::Delivered,
                                metadata: ['provider' => 'instagram'],
                            );
                        }
                    }
                }

                if (isset($event['read']['mid']) && is_string($event['read']['mid']) && $event['read']['mid'] !== '') {
                    $statuses[] = new DeliveryStatusData(
                        providerMessageId: $event['read']['mid'],
                        status: MessageDeliveryStatus::Read,
                        metadata: ['provider' => 'instagram'],
                    );
                }
            }
        }

        return $statuses;
    }

}
