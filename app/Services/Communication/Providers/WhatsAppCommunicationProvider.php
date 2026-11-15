<?php

namespace App\Services\Communication\Providers;

use App\Enums\CommunicationCapability;
use App\Enums\CommunicationErrorCode;
use App\Enums\ConnectedAccountStatus;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageType;
use App\Enums\SocialProvider;
use App\Models\Attachment;
use App\Models\ConnectedAccount;
use App\Services\Communication\AbstractCommunicationProvider;
use App\Services\Communication\DTOs\DeliveryStatusData;
use App\Services\Communication\DTOs\InboundMessageData;
use App\Services\Communication\DTOs\ProviderAccountStatus;
use App\Services\Communication\DTOs\ProviderSendResult;
use App\Services\Communication\DTOs\WebhookVerificationResult;
use Illuminate\Support\Facades\Storage;

/**
 * WhatsApp Cloud API adapter.
 *
 * Text and outbound media are implemented through the Cloud API. Sending
 * business-initiated messages outside a 24-hour customer service window
 * requires approved message templates, which this foundation does not create.
 */
final class WhatsAppCommunicationProvider extends AbstractCommunicationProvider
{
    public function identifier(): string
    {
        return 'whatsapp';
    }

    public function provider(): SocialProvider
    {
        return SocialProvider::WhatsApp;
    }

    public function isConfigured(): bool
    {
        return (bool) (config('social.whatsapp.access_token') || config('social.whatsapp.phone_number_id'));
    }

    public function accountStatus(ConnectedAccount $account): ProviderAccountStatus
    {
        if ($account->provider !== SocialProvider::WhatsApp) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::AccountNotFound,
                'The connected account is not a WhatsApp account.',
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
                'The WhatsApp access token has expired.',
            );
        }

        if ($this->accessToken($account) === null) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::InvalidAccountCredentials,
                'No WhatsApp access token is configured for this account.',
            );
        }

        if ($this->phoneNumberId($account) === null) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::ProviderNotConfigured,
                'No WhatsApp phone number id is configured for this account.',
                configured: false,
            );
        }

        return ProviderAccountStatus::ready();
    }

    public function supports(CommunicationCapability $capability): bool
    {
        return match ($capability) {
            CommunicationCapability::OutboundText => true,
            CommunicationCapability::Attachments => true,
            CommunicationCapability::DeliveryStatus => true,
            CommunicationCapability::InboundWebhooks => true,
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
            return $this->verifyHubChallenge($payload, (string) config('social.whatsapp.webhook_verify_token'));
        }

        $secret = (string) (config('social.whatsapp.app_secret') ?: config('social.facebook.app_secret') ?: config('social.facebook.client_secret'));

        return $this->verifyMetaSignature($secret, $headers, $context);
    }

    protected function verifyHubChallenge(array $payload, string $verifyToken): WebhookVerificationResult
    {
        return parent::verifyHubChallenge($payload, $verifyToken);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $context
     */
    public function resolveAccount(array $payload, array $headers, array $context = []): ?ConnectedAccount
    {
        $phoneNumberId = $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? null;

        if (! is_string($phoneNumberId) || $phoneNumberId === '') {
            return null;
        }

        return ConnectedAccount::query()
            ->where('provider', SocialProvider::WhatsApp->value)
            ->where('status', 'active')
            ->where(function ($query) use ($phoneNumberId): void {
                $query->where('provider_account_id', $phoneNumberId)
                    ->orWhere('metadata->phone_number_id', $phoneNumberId);
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<int, InboundMessageData>
     */
    public function normalizeInbound(array $payload, ConnectedAccount $account, array $context = []): array
    {
        $messages = [];

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $contactName = $value['contacts'][0]['profile']['name'] ?? null;

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    $type = (string) ($message['type'] ?? 'text');
                    $body = $message['text']['body'] ?? $message['button']['text'] ?? null;
                    $sender = isset($message['from']) ? (string) $message['from'] : null;

                    $messages[] = new InboundMessageData(
                        providerMessageId: (string) ($message['id'] ?? ''),
                        body: is_string($body) ? $body : null,
                        senderId: $sender,
                        senderName: is_string($contactName) ? $contactName : null,
                        senderPhone: $sender,
                        providerConversationId: $sender,
                        messageType: $this->whatsAppMessageType($type),
                        sentAt: isset($message['timestamp']) ? now()->setTimestamp((int) $message['timestamp']) : now(),
                        attachments: $this->whatsAppAttachments($message, $type),
                        metadata: [
                            'provider' => 'whatsapp',
                            'phone_number_id' => $value['metadata']['phone_number_id'] ?? null,
                        ],
                    );
                }
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendText(ConnectedAccount $account, string $recipient, string $body, array $context = []): ProviderSendResult
    {
        $token = $this->accessToken($account);
        $phoneNumberId = $this->phoneNumberId($account);

        if ($token === null || $phoneNumberId === null) {
            return ProviderSendResult::failure(CommunicationErrorCode::ProviderNotConfigured, 'WhatsApp is not configured for this account.');
        }

        if (trim($recipient) === '') {
            return ProviderSendResult::failure(CommunicationErrorCode::InvalidRecipient, 'No WhatsApp recipient number is available.');
        }

        return $this->sendPayload($token, $phoneNumberId, [
            'messaging_product' => 'whatsapp',
            'to' => $recipient,
            'type' => 'text',
            'text' => ['body' => $body],
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendAttachment(ConnectedAccount $account, string $recipient, Attachment $attachment, ?string $caption = null, array $context = []): ProviderSendResult
    {
        $token = $this->accessToken($account);
        $phoneNumberId = $this->phoneNumberId($account);

        if ($token === null || $phoneNumberId === null) {
            return ProviderSendResult::failure(CommunicationErrorCode::ProviderNotConfigured, 'WhatsApp is not configured for this account.');
        }

        if (trim($recipient) === '') {
            return ProviderSendResult::failure(CommunicationErrorCode::InvalidRecipient, 'No WhatsApp recipient number is available.');
        }

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->storage_path)) {
            return ProviderSendResult::failure(CommunicationErrorCode::ProviderError, 'The attachment file is no longer available.');
        }

        $contents = $disk->get($attachment->storage_path);

        if ($contents === null) {
            return ProviderSendResult::failure(CommunicationErrorCode::ProviderError, 'The attachment file could not be read.');
        }

        $base = rtrim((string) config('social.whatsapp.api_base_url', 'https://graph.facebook.com/v20.0'), '/');

        try {
            $upload = $this->httpClient()
                ->withToken($token)
                ->attach('file', $contents, $attachment->original_name, ['Content-Type' => $attachment->mime_type])
                ->post("{$base}/{$phoneNumberId}/media", [
                    'messaging_product' => 'whatsapp',
                    'type' => $attachment->mime_type,
                ]);

            $uploadData = $upload->json();
            $uploadData = is_array($uploadData) ? $uploadData : [];

            if (! $upload->successful()) {
                return $this->rejected($upload, $uploadData);
            }

            $mediaId = $uploadData['id'] ?? null;

            if (! is_string($mediaId) || $mediaId === '') {
                return ProviderSendResult::failure(CommunicationErrorCode::ProviderError, 'WhatsApp did not return a media id.');
            }

            $type = $this->whatsAppOutboundType($attachment->mime_type);

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => $type,
                $type => array_filter([
                    'id' => $mediaId,
                    'caption' => $caption,
                    'filename' => $type === 'document' ? $attachment->original_name : null,
                ], static fn ($value): bool => $value !== null),
            ];

            return $this->sendPayload($token, $phoneNumberId, $payload);
        } catch (\Throwable) {
            return $this->networkFailure(new \RuntimeException('whatsapp_media_failed'));
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
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];

                foreach ((array) ($value['statuses'] ?? []) as $status) {
                    if (! is_array($status)) {
                        continue;
                    }

                    $messageId = $status['id'] ?? null;
                    $mapped = $this->deliveryStatus((string) ($status['status'] ?? ''));

                    if (! is_string($messageId) || $messageId === '' || $mapped === null) {
                        continue;
                    }

                    $error = is_array($status['errors'][0] ?? null) ? $status['errors'][0] : [];

                    $statuses[] = new DeliveryStatusData(
                        providerMessageId: $messageId,
                        status: $mapped,
                        occurredAt: isset($status['timestamp']) ? now()->setTimestamp((int) $status['timestamp']) : null,
                        errorCode: isset($error['code']) ? (string) $error['code'] : null,
                        errorMessage: isset($error['title']) ? (string) $error['title'] : null,
                        metadata: ['provider' => 'whatsapp'],
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
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];

                if ((array) ($value['messages'] ?? []) !== []) {
                    $hasMessage = true;
                }

                if ((array) ($value['statuses'] ?? []) !== []) {
                    $hasStatus = true;
                }
            }
        }

        return $hasMessage ? 'inbound_message' : ($hasStatus ? 'delivery_status' : 'webhook_event');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendPayload(string $token, string $phoneNumberId, array $payload): ProviderSendResult
    {
        $base = rtrim((string) config('social.whatsapp.api_base_url', 'https://graph.facebook.com/v20.0'), '/');

        try {
            $response = $this->httpClient()
                ->withToken($token)
                ->post("{$base}/{$phoneNumberId}/messages", $payload);

            $data = $response->json();
            $data = is_array($data) ? $data : [];

            if (! $response->successful()) {
                return $this->rejected($response, $data);
            }

            $messageId = $data['messages'][0]['id'] ?? null;

            if (! is_string($messageId) || $messageId === '') {
                return ProviderSendResult::failure(CommunicationErrorCode::ProviderError, 'WhatsApp accepted the request without returning a message id.');
            }

            return ProviderSendResult::accepted($messageId, ['http_status' => $response->status()]);
        } catch (\Throwable) {
            return $this->networkFailure(new \RuntimeException('whatsapp_http_failed'));
        }
    }

    private function accessToken(ConnectedAccount $account): ?string
    {
        $token = $account->access_token ?: config('social.whatsapp.access_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function phoneNumberId(ConnectedAccount $account): ?string
    {
        $metadata = $account->metadata ?? [];
        $value = $metadata['phone_number_id'] ?? $account->provider_account_id ?: config('social.whatsapp.phone_number_id');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function whatsAppMessageType(string $type): MessageType
    {
        return match ($type) {
            'image' => MessageType::Image,
            'video' => MessageType::Video,
            'audio', 'voice' => MessageType::Audio,
            'document' => MessageType::Document,
            'location' => MessageType::Location,
            'text', 'button', 'interactive' => MessageType::Text,
            default => MessageType::Unknown,
        };
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<int, array<string, mixed>>
     */
    private function whatsAppAttachments(array $message, string $type): array
    {
        $media = $message[$type] ?? null;

        if (! is_array($media)) {
            return [];
        }

        return [[
            'type' => $type,
            'provider_media_id' => isset($media['id']) ? (string) $media['id'] : null,
            'mime_type' => isset($media['mime_type']) ? (string) $media['mime_type'] : null,
            'file_name' => isset($media['filename']) ? (string) $media['filename'] : null,
            'url' => null,
        ]];
    }

    private function whatsAppOutboundType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'document',
        };
    }
}
