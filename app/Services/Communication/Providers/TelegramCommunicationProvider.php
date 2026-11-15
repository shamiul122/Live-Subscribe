<?php

namespace App\Services\Communication\Providers;

use App\Enums\CommunicationCapability;
use App\Enums\CommunicationErrorCode;
use App\Enums\ConnectedAccountStatus;
use App\Enums\MessageType;
use App\Enums\SocialProvider;
use App\Models\Attachment;
use App\Models\ConnectedAccount;
use App\Services\Communication\AbstractCommunicationProvider;
use App\Services\Communication\DTOs\InboundMessageData;
use App\Services\Communication\DTOs\ProviderAccountStatus;
use App\Services\Communication\DTOs\ProviderSendResult;
use App\Services\Communication\DTOs\WebhookVerificationResult;
use Illuminate\Support\Facades\Storage;

/**
 * Telegram Bot API adapter.
 *
 * Telegram does not expose delivery or read receipts through the Bot API, so
 * delivery status callbacks are not supported. Inbound webhooks require either
 * a per-account `webhook_secret_token` in the account metadata or the global
 * TELEGRAM_WEBHOOK_SECRET_TOKEN configuration value.
 */
final class TelegramCommunicationProvider extends AbstractCommunicationProvider
{
    public function identifier(): string
    {
        return 'telegram';
    }

    public function provider(): SocialProvider
    {
        return SocialProvider::Telegram;
    }

    public function isConfigured(): bool
    {
        return (bool) config('social.telegram.bot_token');
    }

    public function accountStatus(ConnectedAccount $account): ProviderAccountStatus
    {
        if ($account->provider !== SocialProvider::Telegram) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::AccountNotFound,
                'The connected account is not a Telegram bot account.',
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
                'The Telegram bot token has expired.',
            );
        }

        if ($this->botToken($account) === null) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::InvalidAccountCredentials,
                'No Telegram bot token is configured for this account.',
            );
        }

        return ProviderAccountStatus::ready();
    }

    public function supports(CommunicationCapability $capability): bool
    {
        return match ($capability) {
            CommunicationCapability::OutboundText => true,
            CommunicationCapability::Attachments => true,
            CommunicationCapability::InboundWebhooks => true,
            CommunicationCapability::DeliveryStatus => false,
        };
    }

    public function capabilityReason(CommunicationCapability $capability): ?string
    {
        return match ($capability) {
            CommunicationCapability::DeliveryStatus => 'The Telegram Bot API does not provide delivery or read receipts.',
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
        $account = $context['account'] ?? null;
        $secret = $account instanceof ConnectedAccount
            ? (string) ($account->metadata['webhook_secret_token'] ?? config('social.telegram.webhook_secret_token'))
            : (string) config('social.telegram.webhook_secret_token');

        if ($secret === '') {
            return WebhookVerificationResult::invalid('provider_not_configured');
        }

        $header = $this->header($headers, 'x-telegram-bot-api-secret-token');

        if (! is_string($header) || ! hash_equals($secret, $header)) {
            return WebhookVerificationResult::invalid('invalid_secret_token');
        }

        return WebhookVerificationResult::valid();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $context
     */
    public function resolveAccount(array $payload, array $headers, array $context = []): ?ConnectedAccount
    {
        $accountId = $context['connected_account_id'] ?? null;

        if (! is_numeric($accountId)) {
            return null;
        }

        return ConnectedAccount::query()
            ->where('provider', SocialProvider::Telegram->value)
            ->where('status', ConnectedAccountStatus::Active->value)
            ->find((int) $accountId);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<int, InboundMessageData>
     */
    public function normalizeInbound(array $payload, ConnectedAccount $account, array $context = []): array
    {
        $message = $payload['message'] ?? $payload['edited_message'] ?? $payload['channel_post'] ?? null;

        if (! is_array($message)) {
            return [];
        }

        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $senderId = isset($from['id']) ? (string) $from['id'] : (isset($chat['id']) ? (string) $chat['id'] : null);

        $name = trim(implode(' ', array_filter([
            isset($from['first_name']) ? (string) $from['first_name'] : null,
            isset($from['last_name']) ? (string) $from['last_name'] : null,
        ])));

        if ($name === '') {
            $name = (string) ($from['username'] ?? $chat['title'] ?? '');
        }

        $body = $message['text'] ?? $message['caption'] ?? null;

        return [new InboundMessageData(
            providerMessageId: (string) ($message['message_id'] ?? ''),
            body: is_string($body) ? $body : null,
            senderId: $senderId,
            senderName: $name !== '' ? $name : null,
            providerConversationId: isset($chat['id']) ? (string) $chat['id'] : $senderId,
            messageType: $this->telegramMessageType($message),
            sentAt: isset($message['date']) ? now()->setTimestamp((int) $message['date']) : now(),
            attachments: $this->telegramAttachments($message),
            metadata: [
                'provider' => 'telegram',
                'chat_type' => $chat['type'] ?? null,
            ],
        )];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendText(ConnectedAccount $account, string $recipient, string $body, array $context = []): ProviderSendResult
    {
        $token = $this->botToken($account);

        if ($token === null) {
            return ProviderSendResult::failure(CommunicationErrorCode::ProviderNotConfigured, 'Telegram is not configured for this account.');
        }

        if (trim($recipient) === '') {
            return ProviderSendResult::failure(CommunicationErrorCode::InvalidRecipient, 'No Telegram chat id is available.');
        }

        return $this->call($token, 'sendMessage', [
            'chat_id' => $recipient,
            'text' => $body,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendAttachment(ConnectedAccount $account, string $recipient, Attachment $attachment, ?string $caption = null, array $context = []): ProviderSendResult
    {
        $token = $this->botToken($account);

        if ($token === null) {
            return ProviderSendResult::failure(CommunicationErrorCode::ProviderNotConfigured, 'Telegram is not configured for this account.');
        }

        if (trim($recipient) === '') {
            return ProviderSendResult::failure(CommunicationErrorCode::InvalidRecipient, 'No Telegram chat id is available.');
        }

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->storage_path)) {
            return ProviderSendResult::failure(CommunicationErrorCode::ProviderError, 'The attachment file is no longer available.');
        }

        $contents = $disk->get($attachment->storage_path);

        if ($contents === null) {
            return ProviderSendResult::failure(CommunicationErrorCode::ProviderError, 'The attachment file could not be read.');
        }

        $method = $this->telegramAttachmentMethod($attachment->mime_type);
        $field = $this->telegramAttachmentField($method);
        $base = rtrim((string) config('social.telegram.api_base_url', 'https://api.telegram.org'), '/');

        try {
            $request = $this->httpClient()->attach($field, $contents, $attachment->original_name);

            $payload = ['chat_id' => $recipient];

            if ($caption !== null && $caption !== '' && $method !== 'sendAudio') {
                $payload['caption'] = $caption;
            }

            $response = $request->post("{$base}/bot{$token}/{$method}", $payload);

            $data = $response->json();
            $data = is_array($data) ? $data : [];

            if (! $response->successful() || ($data['ok'] ?? false) !== true) {
                if ($response->status() === 429) {
                    return ProviderSendResult::failure(CommunicationErrorCode::RateLimited, 'The Telegram rate limit was reached.', retryable: true);
                }

                if ($response->status() === 401 || $response->status() === 403) {
                    return ProviderSendResult::failure(CommunicationErrorCode::InvalidAccountCredentials, 'Telegram rejected the bot token.');
                }

                return ProviderSendResult::failure(CommunicationErrorCode::ProviderRejected, $this->providerErrorMessage($data));
            }

            $messageId = $data['result']['message_id'] ?? null;

            if (! is_int($messageId) && ! is_string($messageId)) {
                return ProviderSendResult::failure(CommunicationErrorCode::ProviderError, 'Telegram accepted the request without returning a message id.');
            }

            return ProviderSendResult::accepted((string) $messageId, ['http_status' => $response->status()]);
        } catch (\Throwable) {
            return $this->networkFailure(new \RuntimeException('telegram_media_failed'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function call(string $token, string $method, array $payload): ProviderSendResult
    {
        $base = rtrim((string) config('social.telegram.api_base_url', 'https://api.telegram.org'), '/');

        try {
            $response = $this->httpClient()->post("{$base}/bot{$token}/{$method}", $payload);

            $data = $response->json();
            $data = is_array($data) ? $data : [];

            if (! $response->successful() || ($data['ok'] ?? false) !== true) {
                if ($response->status() === 429) {
                    return ProviderSendResult::failure(CommunicationErrorCode::RateLimited, 'The Telegram rate limit was reached.', retryable: true);
                }

                if ($response->status() === 401 || $response->status() === 403) {
                    return ProviderSendResult::failure(CommunicationErrorCode::InvalidAccountCredentials, 'Telegram rejected the bot token.');
                }

                return ProviderSendResult::failure(CommunicationErrorCode::ProviderRejected, $this->providerErrorMessage($data));
            }

            $messageId = $data['result']['message_id'] ?? null;

            if (! is_int($messageId) && ! is_string($messageId)) {
                return ProviderSendResult::failure(CommunicationErrorCode::ProviderError, 'Telegram accepted the request without returning a message id.');
            }

            return ProviderSendResult::accepted((string) $messageId, ['http_status' => $response->status()]);
        } catch (\Throwable) {
            return $this->networkFailure(new \RuntimeException('telegram_http_failed'));
        }
    }

    protected function botToken(ConnectedAccount $account): ?string
    {
        $token = $account->access_token ?: config('social.telegram.bot_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function telegramMessageType(array $message): MessageType
    {
        return match (true) {
            isset($message['photo']) => MessageType::Image,
            isset($message['video']) => MessageType::Video,
            isset($message['video_note']) => MessageType::Video,
            isset($message['audio']), isset($message['voice']) => MessageType::Audio,
            isset($message['document']) => MessageType::Document,
            isset($message['location']), isset($message['venue']) => MessageType::Location,
            default => MessageType::Text,
        };
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<int, array<string, mixed>>
     */
    protected function telegramAttachments(array $message): array
    {
        if (isset($message['photo']) && is_array($message['photo']) && $message['photo'] !== []) {
            $photos = array_values($message['photo']);
            $largest = is_array($photos[count($photos) - 1] ?? null) ? $photos[count($photos) - 1] : [];

            return [[
                'type' => 'image',
                'provider_media_id' => isset($largest['file_id']) ? (string) $largest['file_id'] : null,
                'mime_type' => 'image/jpeg',
                'file_name' => null,
                'url' => null,
            ]];
        }

        foreach (['document', 'video', 'audio', 'voice'] as $key) {
            if (isset($message[$key]) && is_array($message[$key])) {
                return [[
                    'type' => $key,
                    'provider_media_id' => isset($message[$key]['file_id']) ? (string) $message[$key]['file_id'] : null,
                    'mime_type' => isset($message[$key]['mime_type']) ? (string) $message[$key]['mime_type'] : null,
                    'file_name' => isset($message[$key]['file_name']) ? (string) $message[$key]['file_name'] : null,
                    'url' => null,
                ]];
            }
        }

        return [];
    }

    protected function telegramAttachmentMethod(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'sendPhoto',
            str_starts_with($mimeType, 'video/') => 'sendVideo',
            str_starts_with($mimeType, 'audio/') => 'sendAudio',
            default => 'sendDocument',
        };
    }

    protected function telegramAttachmentField(string $method): string
    {
        return match ($method) {
            'sendPhoto' => 'photo',
            'sendVideo' => 'video',
            'sendAudio' => 'audio',
            default => 'document',
        };
    }

    protected function header(array $headers, string $name): ?string
    {
        $lower = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $lower) {
                return $value !== '' ? $value : null;
            }
        }
        return null;
    }

    protected function providerErrorMessage(array $data): string
    {
        return (string) ($data['description'] ?? $data['message'] ?? 'provider_rejected');
    }
}
