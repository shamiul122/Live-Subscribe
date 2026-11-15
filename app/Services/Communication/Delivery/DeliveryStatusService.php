<?php

namespace App\Services\Communication\Delivery;

use App\Enums\DeliveryAttemptStatus;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\ConnectedAccount;
use App\Models\DeliveryAttempt;
use App\Models\Message;
use App\Services\Communication\CommunicationNotifier;
use App\Services\Communication\DTOs\DeliveryStatusData;
use App\Services\Inbox\ConversationEventService;

/**
 * Applies provider delivery callbacks to outbound messages.
 *
 * Transitions are monotonic: a delayed `sent` callback can never overwrite a
 * `read` state. A failure callback is recorded and notified once.
 */
final class DeliveryStatusService
{
    public function __construct(
        private readonly CommunicationNotifier $notifier,
        private readonly ConversationEventService $events,
    ) {
    }

    /**
     * @param  array<int, DeliveryStatusData>  $statuses
     */
    public function apply(ConnectedAccount $account, array $statuses): int
    {
        $updated = 0;

        foreach ($statuses as $status) {
            if ($status->providerMessageId === '') {
                continue;
            }

            $message = Message::query()
                ->where('organization_id', $account->organization_id)
                ->where('provider_message_id', $status->providerMessageId)
                ->where('direction', MessageDirection::Outbound->value)
                ->first();

            if ($message === null) {
                continue;
            }

            $current = $message->delivery_status ?? MessageDeliveryStatus::Queued;

            // Ignore duplicate and out-of-order callbacks.
            if ($status->status->rank() <= $current->rank()) {
                continue;
            }

            $message->delivery_status = $status->status;

            if ($status->status === MessageDeliveryStatus::Delivered || $status->status === MessageDeliveryStatus::Read) {
                $message->delivered_at = $status->occurredAt ?? now();
            }

            if ($status->status === MessageDeliveryStatus::Read) {
                $message->read_at = $status->occurredAt ?? now();
            }

            if ($status->status === MessageDeliveryStatus::Failed) {
                $message->failed_at = $status->occurredAt ?? now();
                $message->failure_reason = $status->errorMessage
                    ?? ($status->errorCode !== null ? 'Provider error '.$status->errorCode : 'The provider reported a delivery failure.');
            }

            $message->metadata = array_merge($message->metadata ?? [], [
                'provider_status' => $status->status->value,
                'provider_error_code' => $status->errorCode,
            ]);
            $message->save();

            $this->updateAttempt($message, $status);

            if ($message->conversation !== null) {
                $event = match ($status->status) {
                    MessageDeliveryStatus::Delivered, MessageDeliveryStatus::Read => ConversationEventService::MESSAGE_DELIVERED,
                    MessageDeliveryStatus::Failed => ConversationEventService::MESSAGE_FAILED,
                    default => null,
                };

                if ($event !== null) {
                    $this->events->record(null, $message->organization, $message->conversation, $event, [
                        'conversation_id' => $message->conversation_id,
                        'message_id' => $message->id,
                        'delivery_status' => $status->status->value,
                        'provider_message_id' => $status->providerMessageId,
                    ]);
                }
            }

            if ($status->status === MessageDeliveryStatus::Failed) {
                $this->notifier->notifyDeliveryFailure($message);
            }

            $updated++;
        }

        return $updated;
    }

    private function updateAttempt(Message $message, DeliveryStatusData $status): void
    {
        $attempt = DeliveryAttempt::query()
            ->where('message_id', $message->id)
            ->where('provider_message_id', $status->providerMessageId)
            ->latest('attempt_number')
            ->first();

        if ($attempt === null) {
            $attempt = DeliveryAttempt::query()
                ->where('message_id', $message->id)
                ->whereNull('provider_message_id')
                ->latest('attempt_number')
                ->first();
        }

        if ($attempt === null) {
            return;
        }

        $attempt->status = DeliveryAttemptStatus::fromMessageStatus($status->status);
        $attempt->completed_at = $status->occurredAt ?? now();

        if ($status->status === MessageDeliveryStatus::Failed) {
            $attempt->error_code = $status->errorCode;
            $attempt->error_message = $status->errorMessage;
        }

        $attempt->metadata = array_merge($attempt->metadata ?? [], [
            'last_provider_status' => $status->status->value,
        ]);
        $attempt->save();
    }
}
