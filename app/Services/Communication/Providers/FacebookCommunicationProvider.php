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
use Illuminate\Support\Str;

/**
 * Facebook Page Messenger adapter.
 *
 * Requires a Facebook Page access token on the connected account and the
 * `pages_messaging` permission. Inbound webhooks require the `messages` field
 * subscription and the Meta app secret for signature verification.
 */
final class FacebookCommunicationProvider extends AbstractCommunicationProvider
{
    public function identifier(): string
    {
        return 'facebook';
    }

    public function provider(): SocialProvider
    {
        return SocialProvider::Facebook;
    }

    public function isConfigured(): bool
    {
        return (bool) config('social.facebook.client_id') && (bool) config('social.facebook.client_secret');
    }

    public function accountStatus(ConnectedAccount $account): ProviderAccountStatus
    {
        if (! $this->isConfigured()) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::ProviderNotConfigured,
                'The Facebook app is not configured.',
                configured: false,
            );
        }

        if ($account->provider !== SocialProvider::Facebook) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::AccountNotFound,
                'The connected account is not a Facebook page account.',
            );
        }

        if ($account->status !== \App\Enums\ConnectedAccountStatus::Active) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::InvalidAccountCredentials,
                'The connected Facebook account is not active.',
            );
        }

        if ($account->token_expires_at !== null && $account->token_expires_at->isPast()) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::InvalidAccountCredentials,
                'The Facebook page access token has expired.',
            );
        }

        if ((string) $account->provider_account_id === '') {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::InvalidAccountCredentials,
                'The connected Facebook account has no page identifier.',
            );
        }

        if (! is_string($account->access_token) || $account->access_token === '') {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::InvalidAccountCredentials,
                'No Facebook page access token is available for this account.',
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
            CommunicationCapability::Attachments => 'Outbound Facebook attachments require a publicly reachable media URL and are not enabled in this foundation.',
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
            return $this->verifyHubChallenge($payload, (string) config('social.facebook.webhook_verify_token'));
        }

        $secret = (string) (config('social.facebook.app_secret') ?: config('social.facebook.client_secret'));

        return $this->verifyMetaSignature($secret, $headers, $context);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $context
     */
    public function resolveAccount(array $payload, array $headers, array $context = []): ?ConnectedAccount
    {
        $pageId = $this->metaEntryId($payload);

        if ($pageId === null || $pageId === '') {
            return null;
        }

        return ConnectedAccount::query()
            ->where('provider', SocialProvider::Facebook->value)
            ->where('provider_account_id', $pageId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<int, InboundMessageData>
     */
    public function normalizeInbound(array $payload, ConnectedAccount $account, array $context = []): array
    {
        return $this->normalizeMetaMessaging($payload);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendText(ConnectedAccount $account, string $recipient, string $body, array $context = []): ProviderSendResult
    {
        if (trim($recipient) === '') {
            return ProviderSendResult::failure(CommunicationErrorCode::InvalidRecipient, 'No Facebook recipient is available.');
        }

        $status = $this->accountStatus($account);

        if (! $status->usable) {
            return ProviderSendResult::failure($status->errorCode ?? CommunicationErrorCode::InvalidAccountCredentials, $status->errorMessage);
        }

        $base = rtrim((string) config('social.facebook.api_base_url', 'https://graph.facebook.com/v20.0'), '/');
        $pageId = rawurlencode((string) $account->provider_account_id);

        try {
            $response = $this->httpClient()
                ->withToken((string) $account->access_token)
                ->post("{$base}/{$pageId}/messages", [
                    'recipient' => ['id' => $recipient],
                    'messaging_type' => 'RESPONSE',
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
                    'Facebook accepted the request without returning a message id.',
                );
            }

            return ProviderSendResult::accepted($messageId, ['http_status' => $response->status()]);
        } catch (\Throwable) {
            return $this->networkFailure(new \RuntimeException('facebook_http_failed'));
        }
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
                                occurredAt: isset($event['timestamp']) ? now()->setTimestamp((int) floor(((int) $event['timestamp']) / 1000)) : null,
                                metadata: ['provider' => 'facebook'],
                            );
                        }
                    }
                }

                if (isset($event['read']['mid']) && is_string($event['read']['mid']) && $event['read']['mid'] !== '') {
                    $statuses[] = new DeliveryStatusData(
                        providerMessageId: $event['read']['mid'],
                        status: MessageDeliveryStatus::Read,
                        occurredAt: isset($event['timestamp']) ? now()->setTimestamp((int) floor(((int) $event['timestamp']) / 1000)) : null,
                        metadata: ['provider' => 'facebook'],
                    );
                }
            }
        }

        return $statuses;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function eventType(array $payload): string
    {
        $hasMessage = false;
        $hasStatus = false;

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['messaging'] ?? []) as $event) {
                if (! is_array($event)) {
                    continue;
                }

                if (isset($event['message'])) {
                    $hasMessage = true;
                }

                if (isset($event['delivery']) || isset($event['read'])) {
                    $hasStatus = true;
                }
            }
        }

        return $hasMessage ? 'inbound_message' : ($hasStatus ? 'delivery_status' : 'webhook_event');
    }

    public function providerEventId(array $payload): ?string
    {
        $event = $payload['entry'][0]['messaging'][0]['message']['mid'] ?? null;

        return is_string($event) && $event !== '' ? \Illuminate\Support\Str::limit($event, 160, '') : null;
    }
}
