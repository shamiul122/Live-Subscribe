<?php

namespace App\Services\Communication;

use App\Contracts\Communication\CommunicationProviderInterface;
use App\Enums\CommunicationCapability;
use App\Enums\CommunicationErrorCode;
use App\Enums\ConnectedAccountStatus;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageType;
use App\Enums\SocialProvider;
use App\Models\Attachment;
use App\Models\ConnectedAccount;
use App\Services\Communication\DTOs\DeliveryStatusData;
use App\Services\Communication\DTOs\InboundMessageData;
use App\Services\Communication\DTOs\ProviderAccountStatus;
use App\Services\Communication\DTOs\ProviderSendResult;
use App\Services\Communication\DTOs\WebhookVerificationResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Shared, provider-agnostic adapter behaviour.
 *
 * No helper here ever logs or returns an access token. Provider error messages
 * are truncated and only used for display/diagnostics.
 */
abstract class AbstractCommunicationProvider implements CommunicationProviderInterface
{
    abstract public function identifier(): string;

    public function provider(): SocialProvider
    {
        return SocialProvider::from($this->identifier());
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function accountStatus(ConnectedAccount $account): ProviderAccountStatus
    {
        if (! $this->isConfigured()) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::ProviderNotConfigured,
                'The provider application is not configured.',
                configured: false,
            );
        }

        if ($account->provider !== $this->provider()) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::AccountNotFound,
                'The connected account does not belong to this provider.',
            );
        }

        if ($account->status !== ConnectedAccountStatus::Active) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::InvalidAccountCredentials,
                'The connected account is not active.',
            );
        }

        if ($account->token_expires_at !== null && $account->token_expires_at->isPast()) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::InvalidAccountCredentials,
                'The provider access token has expired.',
            );
        }

        return ProviderAccountStatus::ready();
    }

    public function supports(CommunicationCapability $capability): bool
    {
        return false;
    }

    public function capabilityReason(CommunicationCapability $capability): ?string
    {
        return match ($capability) {
            CommunicationCapability::OutboundText => 'Outbound text messages are not supported by this provider.',
            CommunicationCapability::Attachments => 'Attachment delivery is not supported by this provider.',
            CommunicationCapability::DeliveryStatus => 'This provider does not send delivery status callbacks.',
            CommunicationCapability::InboundWebhooks => 'Inbound webhooks are not supported by this provider.',
        };
    }

    /**
     * @return array<string, bool>
     */
    public function capabilities(): array
    {
        $result = [];

        foreach (CommunicationCapability::cases() as $capability) {
            $result[$capability->value] = $this->supports($capability);
        }

        return $result;
    }

    public function verifyWebhook(string $method, array $payload, array $headers, array $context = []): WebhookVerificationResult
    {
        return WebhookVerificationResult::invalid('not_implemented');
    }

    public function resolveAccount(array $payload, array $headers, array $context = []): ?ConnectedAccount
    {
        return null;
    }

    public function normalizeInbound(array $payload, ConnectedAccount $account, array $context = []): array
    {
        return [];
    }

    public function sendText(ConnectedAccount $account, string $recipient, string $body, array $context = []): ProviderSendResult
    {
        return ProviderSendResult::failure(
            CommunicationErrorCode::CapabilityNotSupported,
            'Outbound text messages are not supported by this provider.',
        );
    }

    public function sendAttachment(ConnectedAccount $account, string $recipient, Attachment $attachment, ?string $caption = null, array $context = []): ProviderSendResult
    {
        return ProviderSendResult::failure(
            CommunicationErrorCode::CapabilityNotSupported,
            'Attachment delivery is not supported by this provider.',
        );
    }

    public function normalizeDeliveryStatus(array $payload, ConnectedAccount $account): array
    {
        return [];
    }

    public function providerEventId(array $payload): ?string
    {
        $value = $payload['entry'][0]['id'] ?? $payload['update_id'] ?? null;

        return is_scalar($value) ? Str::limit((string) $value, 160, '') : null;
    }

    public function eventType(array $payload): string
    {
        return 'webhook_event';
    }

    protected function httpClient(int $timeout = 30): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout($timeout)->acceptJson();
    }

    /**
     * Meta (Facebook/Instagram/WhatsApp) GET challenge verification.
     *
     * @param  array<string, mixed>  $query
     */
    protected function verifyHubChallenge(array $query, string $expectedToken): WebhookVerificationResult
    {
        $mode = $query['hub_mode'] ?? $query['hub.mode'] ?? null;

        if ($mode !== 'subscribe') {
            return WebhookVerificationResult::invalid('invalid_mode');
        }

        $token = $query['hub_verify_token'] ?? $query['hub.verify_token'] ?? null;

        if (! is_string($token) || ! hash_equals($expectedToken, $token)) {
            return WebhookVerificationResult::invalid('invalid_verify_token');
        }

        $challenge = $query['hub_challenge'] ?? $query['hub.challenge'] ?? null;

        if (! is_scalar($challenge) || (string) $challenge === '') {
            return WebhookVerificationResult::invalid('missing_challenge');
        }

        return WebhookVerificationResult::valid((string) $challenge);
    }

    /**
     * Verify the `X-Hub-Signature-256` header against the exact raw request body.
     *
     * @param  array<string, string>  $headers  Lower-cased header names.
     * @param  array<string, mixed>  $context
     */
    protected function verifyMetaSignature(string $secret, array $headers, array $context): WebhookVerificationResult
    {
        if ($secret === '') {
            return WebhookVerificationResult::invalid('provider_not_configured');
        }

        $rawBody = $context['raw_body'] ?? null;

        if (! is_string($rawBody)) {
            return WebhookVerificationResult::invalid('missing_raw_body');
        }

        $header = $this->header($headers, 'x-hub-signature-256');

        if (! is_string($header) || ! str_starts_with($header, 'sha256=')) {
            return WebhookVerificationResult::invalid('missing_signature');
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        if (! hash_equals($expected, $header)) {
            return WebhookVerificationResult::invalid('invalid_signature');
        }

        return WebhookVerificationResult::valid();
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function header(array $headers, string $name): ?string
    {
        $value = $headers[strtolower($name)] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Normalize a failed provider HTTP response without exposing secrets.
     *
     * @param  array<string, mixed>  $data
     */
    protected function rejected(Response $response, array $data = []): ProviderSendResult
    {
        $status = $response->status();
        $message = $this->providerErrorMessage($data);

        return match (true) {
            $status === 429 => ProviderSendResult::failure(
                CommunicationErrorCode::RateLimited,
                $message ?? 'The provider rate limit was reached.',
                retryable: true,
            ),
            in_array($status, [401, 403], true) => ProviderSendResult::failure(
                CommunicationErrorCode::InvalidAccountCredentials,
                $message ?? 'The provider rejected the account credentials.',
            ),
            $status >= 500 => ProviderSendResult::failure(
                CommunicationErrorCode::ProviderError,
                $message ?? 'The provider returned a server error.',
                retryable: true,
            ),
            default => ProviderSendResult::failure(
                CommunicationErrorCode::ProviderRejected,
                $message ?? 'The provider rejected the message.',
            ),
        };
    }

    /**
     * Ambiguous transport failures are never auto-retried. The operator can
     * retry explicitly from the inbox after checking the provider.
     */
    protected function networkFailure(\Throwable $exception): ProviderSendResult
    {
        return ProviderSendResult::failure(
            CommunicationErrorCode::NetworkError,
            'The provider could not be reached. Check the network and retry manually.',
            retryable: false,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function providerErrorMessage(array $data): ?string
    {
        $error = $data['error'] ?? null;

        if (is_string($error) && $error !== '') {
            return Str::limit($error, 300, '');
        }

        if (is_array($error)) {
            $message = $error['message'] ?? $error['title'] ?? $error['description'] ?? null;

            if (is_scalar($message) && (string) $message !== '') {
                return Str::limit((string) $message, 300, '');
            }
        }

        if (isset($data['description']) && is_scalar($data['description'])) {
            return Str::limit((string) $data['description'], 300, '');
        }

        return null;
    }

    /**
     * Normalize Meta Messenger `entry[].messaging[]` message events.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, InboundMessageData>
     */
    protected function normalizeMetaMessaging(array $payload): array
    {
        $messages = [];

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ((array) ($entry['messaging'] ?? []) as $event) {
                if (! is_array($event)) {
                    continue;
                }

                $message = $event['message'] ?? null;

                if (! is_array($message) || ($message['is_echo'] ?? false) === true) {
                    continue;
                }

                $sender = is_array($event['sender'] ?? null) ? $event['sender'] : [];
                $senderId = isset($sender['id']) ? (string) $sender['id'] : null;
                $text = isset($message['text']) && is_string($message['text']) ? $message['text'] : null;
                $attachments = $this->metaAttachments($message);

                $messages[] = new InboundMessageData(
                    providerMessageId: (string) ($message['mid'] ?? ''),
                    body: $text,
                    senderId: $senderId,
                    senderName: null,
                    providerConversationId: $senderId,
                    messageType: $this->metaMessageType($attachments, $text),
                    sentAt: isset($event['timestamp']) ? now()->setTimestamp((int) floor(((int) $event['timestamp']) / 1000)) : now(),
                    attachments: $attachments,
                    metadata: [
                        'provider' => $this->identifier(),
                        'source' => 'webhook',
                    ],
                );
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function metaEntryId(array $payload): ?string
    {
        $value = $payload['entry'][0]['id'] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<int, array<string, mixed>>
     */
    private function metaAttachments(array $message): array
    {
        $result = [];

        foreach ((array) ($message['attachments'] ?? []) as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $result[] = [
                'type' => isset($attachment['type']) ? (string) $attachment['type'] : 'file',
                'provider_media_id' => null,
                'mime_type' => null,
                'file_name' => null,
                'url' => isset($attachment['payload']['url']) ? (string) $attachment['payload']['url'] : null,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     */
    private function metaMessageType(array $attachments, ?string $text): MessageType
    {
        if ($text !== null && $text !== '') {
            return MessageType::Text;
        }

        $type = $attachments[0]['type'] ?? null;

        return match ($type) {
            'image' => MessageType::Image,
            'video' => MessageType::Video,
            'audio' => MessageType::Audio,
            'file' => MessageType::Document,
            default => $attachments !== [] ? MessageType::Document : MessageType::Unknown,
        };
    }

    protected function deliveryStatus(string $status): ?MessageDeliveryStatus
    {
        return match (strtolower($status)) {
            'sent' => MessageDeliveryStatus::Sent,
            'delivered' => MessageDeliveryStatus::Delivered,
            'read' => MessageDeliveryStatus::Read,
            'failed' => MessageDeliveryStatus::Failed,
            default => null,
        };
    }
}
