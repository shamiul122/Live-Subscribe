<?php

namespace App\Services\Communication\Providers;

use App\Enums\CommunicationCapability;
use App\Enums\CommunicationErrorCode;
use App\Enums\ConnectedAccountStatus;
use App\Enums\SocialProvider;
use App\Models\Attachment;
use App\Models\ConnectedAccount;
use App\Services\Communication\AbstractCommunicationProvider;
use App\Services\Communication\DTOs\ProviderAccountStatus;
use App\Services\Communication\DTOs\ProviderSendResult;
use App\Services\Communication\DTOs\WebhookVerificationResult;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Email adapter backed by the application's configured Laravel mail transport.
 *
 * The transport (SMTP/API) is the provider. A success result means the transport
 * accepted the message; it is never treated as proof of inbox delivery. Inbound
 * email parsing and delivery receipts are not enabled.
 */
final class EmailCommunicationProvider extends AbstractCommunicationProvider
{
    public function identifier(): string
    {
        return 'email';
    }

    public function provider(): SocialProvider
    {
        return SocialProvider::Email;
    }

    public function isConfigured(): bool
    {
        $mailer = (string) config('mail.default', '');
        $from = (string) config('mail.from.address', '');

        return $mailer !== '' && $mailer !== 'log' && $from !== '';
    }

    public function accountStatus(ConnectedAccount $account): ProviderAccountStatus
    {
        if ($account->provider !== SocialProvider::Email) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::AccountNotFound,
                'The connected account is not an email account.',
            );
        }

        if (! $this->isConfigured()) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::ProviderNotConfigured,
                'No usable mail transport is configured.',
                configured: false,
            );
        }

        if ($account->status !== ConnectedAccountStatus::Active) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::InvalidAccountCredentials,
                'The connected account is not active.',
            );
        }

        if (filter_var((string) $account->provider_account_id, FILTER_VALIDATE_EMAIL) === false) {
            return ProviderAccountStatus::failure(
                CommunicationErrorCode::InvalidAccountCredentials,
                'The connected email account has no valid sender address.',
            );
        }

        return ProviderAccountStatus::ready();
    }

    public function supports(CommunicationCapability $capability): bool
    {
        return match ($capability) {
            CommunicationCapability::OutboundText => true,
            CommunicationCapability::Attachments => true,
            CommunicationCapability::DeliveryStatus => false,
            CommunicationCapability::InboundWebhooks => false,
        };
    }

    public function capabilityReason(CommunicationCapability $capability): ?string
    {
        return match ($capability) {
            CommunicationCapability::DeliveryStatus => 'No email delivery receipt provider is configured.',
            CommunicationCapability::InboundWebhooks => 'No inbound email parsing provider is configured.',
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
        return WebhookVerificationResult::invalid('capability_not_supported');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendText(ConnectedAccount $account, string $recipient, string $body, array $context = []): ProviderSendResult
    {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return ProviderSendResult::failure(CommunicationErrorCode::InvalidRecipient, 'No valid email recipient is available.');
        }

        $status = $this->accountStatus($account);

        if (! $status->usable) {
            return ProviderSendResult::failure($status->errorCode ?? CommunicationErrorCode::InvalidAccountCredentials, $status->errorMessage);
        }

        try {
            Mail::raw($body, function ($message) use ($account, $recipient, $context): void {
                $message->from((string) $account->provider_account_id, $account->account_name)
                    ->to($recipient)
                    ->subject((string) ($context['subject'] ?? 'New message'));
            });

            return ProviderSendResult::acceptedWithoutId(['transport' => (string) config('mail.default')]);
        } catch (\Throwable $exception) {
            return ProviderSendResult::failure(
                CommunicationErrorCode::ProviderRejected,
                'The mail transport rejected the message.',
                retryable: false,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendAttachment(ConnectedAccount $account, string $recipient, Attachment $attachment, ?string $caption = null, array $context = []): ProviderSendResult
    {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return ProviderSendResult::failure(CommunicationErrorCode::InvalidRecipient, 'No valid email recipient is available.');
        }

        $status = $this->accountStatus($account);

        if (! $status->usable) {
            return ProviderSendResult::failure($status->errorCode ?? CommunicationErrorCode::InvalidAccountCredentials, $status->errorMessage);
        }

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->storage_path)) {
            return ProviderSendResult::failure(CommunicationErrorCode::ProviderError, 'The attachment file is no longer available.');
        }

        $contents = $disk->get($attachment->storage_path);

        if ($contents === null) {
            return ProviderSendResult::failure(CommunicationErrorCode::ProviderError, 'The attachment file could not be read.');
        }

        try {
            Mail::raw($caption ?? 'Please find the attached file.', function ($message) use ($account, $recipient, $context, $attachment, $contents): void {
                $message->from((string) $account->provider_account_id, $account->account_name)
                    ->to($recipient)
                    ->subject((string) ($context['subject'] ?? 'New message'))
                    ->attachData($contents, $attachment->original_name, ['mime' => $attachment->mime_type]);
            });

            return ProviderSendResult::acceptedWithoutId(['transport' => (string) config('mail.default')]);
        } catch (\Throwable $exception) {
            return ProviderSendResult::failure(
                CommunicationErrorCode::ProviderRejected,
                'The mail transport rejected the attachment.',
                retryable: false,
            );
        }
    }
}
