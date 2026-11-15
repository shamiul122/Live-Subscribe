<?php

namespace App\Jobs\Communication;

use App\Enums\CommunicationErrorCode;
use App\Enums\DeliveryAttemptStatus;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\ConnectedAccount;
use App\Models\DeliveryAttempt;
use App\Models\Message;
use App\Services\Communication\CommunicationProviderRegistry;
use App\Services\Communication\CommunicationNotifier;
use App\Services\Communication\DTOs\ProviderSendResult;
use App\Services\Communication\RecipientResolver;
use App\Services\Inbox\ConversationEventService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends one queued outbound message.
 *
 * The job claims the queued row atomically, so a duplicated dispatch cannot
 * send the same message twice. It has a single attempt: ambiguous provider or
 * network failures are surfaced to the user and require an explicit retry.
 */
class SendMessageJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public int $uniqueFor = 600;

    public function __construct(
        public int $messageId,
        public int $connectedAccountId,
        public string $idempotencyKey,
    ) {
        $this->timeout = (int) config('communication.outbound.timeout', 30);
    }

    public function uniqueId(): string
    {
        return $this->idempotencyKey;
    }

    public function handle(
        CommunicationProviderRegistry $providers,
        RecipientResolver $recipients,
        CommunicationNotifier $notifier,
        ConversationEventService $events,
    ): void {
        $claimed = Message::query()
            ->whereKey($this->messageId)
            ->where('direction', MessageDirection::Outbound->value)
            ->where('delivery_status', MessageDeliveryStatus::Queued->value)
            ->update([
                'delivery_status' => MessageDeliveryStatus::Sending->value,
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            // Already sent, already failed, or another worker owns it.
            return;
        }

        $message = Message::query()->with(['conversation.contact', 'conversation.connectedAccount', 'attachments'])->find($this->messageId);

        if ($message === null) {
            return;
        }

        $connectedAccount = ConnectedAccount::query()->find($this->connectedAccountId);

        if ($connectedAccount === null) {
            $this->failMessage($message, null, CommunicationErrorCode::AccountNotFound, 'The connected account no longer exists.', $notifier, $events);

            return;
        }

        if ($connectedAccount->organization_id !== $message->organization_id) {
            $this->failMessage($message, $connectedAccount, CommunicationErrorCode::AccountNotFound, 'The connected account does not belong to the message organization.', $notifier, $events);

            return;
        }

        $attemptNumber = (int) DeliveryAttempt::query()
            ->where('message_id', $message->id)
            ->max('attempt_number') + 1;

        $attempt = DeliveryAttempt::query()->create([
            'organization_id' => $message->organization_id,
            'conversation_id' => $message->conversation_id,
            'message_id' => $message->id,
            'connected_account_id' => $connectedAccount->id,
            'provider' => $connectedAccount->provider->value,
            'attempt_number' => $attemptNumber,
            'status' => DeliveryAttemptStatus::Processing,
            'attempted_at' => now(),
            'metadata' => ['idempotency_key' => $this->idempotencyKey],
        ]);

        $provider = $providers->providerForAccount($connectedAccount);

        if ($provider === null) {
            $this->failAttempt($attempt, $message, $connectedAccount, CommunicationErrorCode::ProviderNotConfigured, 'No communication adapter exists for this provider.', $notifier, $events);

            return;
        }

        $accountStatus = $provider->accountStatus($connectedAccount);

        if (! $accountStatus->usable) {
            $this->failAttempt(
                $attempt,
                $message,
                $connectedAccount,
                $accountStatus->errorCode ?? CommunicationErrorCode::InvalidAccountCredentials,
                $accountStatus->errorMessage ?? 'The connected account cannot send messages right now.',
                $notifier,
                $events,
            );

            return;
        }

        try {
            $recipient = $recipients->resolve($message->organization, $message->conversation, $connectedAccount);
        } catch (Throwable $exception) {
            $this->failAttempt($attempt, $message, $connectedAccount, CommunicationErrorCode::InvalidRecipient, $this->safeMessage($exception), $notifier, $events);

            return;
        }

        $context = [
            'subject' => $message->conversation?->subject,
            'organization_id' => $message->organization_id,
            'conversation_id' => $message->conversation_id,
            'message_id' => $message->id,
        ];

        $attachments = $message->attachments;
        $results = [];

        try {
            if ($attachments->isEmpty()) {
                $results[] = $provider->sendText($connectedAccount, $recipient, (string) ($message->body ?? ''), $context);
            } else {
                foreach ($attachments->values() as $index => $attachment) {
                    $caption = $index === 0 && $message->body !== null && $message->body !== '' ? $message->body : null;
                    $results[] = $provider->sendAttachment($connectedAccount, $recipient, $attachment, $caption, $context);
                }
            }
        } catch (Throwable $exception) {
            $this->failAttempt($attempt, $message, $connectedAccount, CommunicationErrorCode::ProviderError, $this->safeMessage($exception), $notifier, $events);

            return;
        }

        $failure = null;

        foreach ($results as $result) {
            if (! $result->isSuccessful()) {
                $failure = $result;
                break;
            }
        }

        if ($failure instanceof ProviderSendResult) {
            $sentCount = count(array_filter($results, fn (ProviderSendResult $result): bool => $result->isSuccessful()));
            $reason = $failure->errorMessage ?? $failure->errorCode?->label() ?? 'The provider rejected the message.';

            if ($sentCount > 0) {
                $reason = 'Partially sent ('.$sentCount.' of '.count($results).' parts): '.$reason;
            }

            $this->failAttempt(
                $attempt,
                $message,
                $connectedAccount,
                $failure->errorCode ?? CommunicationErrorCode::ProviderRejected,
                $reason,
                $notifier,
                $events,
            );

            return;
        }

        $this->markSent($attempt, $message, $connectedAccount, $results, $events);
    }

    /**
     * @param  array<int, ProviderSendResult>  $results
     */
    private function markSent(
        DeliveryAttempt $attempt,
        Message $message,
        ConnectedAccount $connectedAccount,
        array $results,
        ConversationEventService $events,
    ): void {
        $providerIds = [];

        foreach ($results as $result) {
            if ($result->providerMessageId !== null && $result->providerMessageId !== '') {
                $providerIds[] = $result->providerMessageId;
            }
        }

        $message->provider_message_id = $providerIds[0] ?? $message->provider_message_id;
        $message->delivery_status = MessageDeliveryStatus::Sent;
        $message->sent_at = now();
        $message->delivered_at = null;
        $message->read_at = null;
        $message->failed_at = null;
        $message->failure_reason = null;
        $message->metadata = array_merge($message->metadata ?? [], [
            'provider_message_ids' => $providerIds,
            'provider' => $connectedAccount->provider->value,
        ]);
        $message->save();

        $attempt->status = DeliveryAttemptStatus::Sent;
        $attempt->provider_message_id = $providerIds[0] ?? null;
        $attempt->error_code = null;
        $attempt->error_message = null;
        $attempt->completed_at = now();
        $attempt->metadata = array_merge($attempt->metadata ?? [], [
            'parts' => count($results),
            'provider_message_ids' => $providerIds,
        ]);
        $attempt->save();

        $conversation = $message->conversation;

        if ($conversation !== null) {
            $events->record(null, $message->organization, $conversation, ConversationEventService::MESSAGE_SENT, [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'provider_message_id' => $providerIds[0] ?? null,
            ]);
        }
    }

    private function failAttempt(
        DeliveryAttempt $attempt,
        Message $message,
        ConnectedAccount $connectedAccount,
        CommunicationErrorCode $code,
        string $reason,
        CommunicationNotifier $notifier,
        ConversationEventService $events,
    ): void {
        $attempt->status = DeliveryAttemptStatus::Failed;
        $attempt->error_code = $code->value;
        $attempt->error_message = Str::limit($reason, 500, '');
        $attempt->completed_at = now();
        $attempt->metadata = array_merge($attempt->metadata ?? [], ['provider' => $connectedAccount->provider->value]);
        $attempt->save();

        $this->failMessage($message, $connectedAccount, $code, $reason, $notifier, $events);
    }

    private function failMessage(
        Message $message,
        ?ConnectedAccount $connectedAccount,
        CommunicationErrorCode $code,
        string $reason,
        CommunicationNotifier $notifier,
        ConversationEventService $events,
    ): void {
        $message->delivery_status = MessageDeliveryStatus::Failed;
        $message->failed_at = now();
        $message->failure_reason = Str::limit($reason, 500, '');
        $message->metadata = array_merge($message->metadata ?? [], [
            'provider_error_code' => $code->value,
        ]);
        $message->save();

        $conversation = $message->conversation;

        if ($conversation !== null) {
            $events->record(null, $message->organization, $conversation, ConversationEventService::MESSAGE_FAILED, [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'error_code' => $code->value,
            ]);
        }

        $notifier->notifyDeliveryFailure($message);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendMessageJob failed', [
            'message_id' => $this->messageId,
            'error' => Str::limit($exception->getMessage(), 300, ''),
        ]);

        $message = Message::query()->find($this->messageId);

        if ($message === null || $message->delivery_status !== MessageDeliveryStatus::Sending) {
            return;
        }

        $message->delivery_status = MessageDeliveryStatus::Failed;
        $message->failed_at = now();
        $message->failure_reason = 'The message job stopped unexpectedly. Retry when the queue is healthy.';
        $message->save();

        app(CommunicationNotifier::class)->notifyDeliveryFailure($message);
    }

    private function safeMessage(Throwable $exception): string
    {
        return Str::limit($exception->getMessage() !== '' ? $exception->getMessage() : 'The provider call failed.', 300, '');
    }
}
