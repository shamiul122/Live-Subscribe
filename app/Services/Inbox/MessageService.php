<?php

namespace App\Services\Inbox;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

/**
 * Stores inbound and outbound messages inside an organization-scoped
 * conversation.
 *
 * Outbound messages are persisted in the `queued` state before the sending
 * job runs, so a queued message is visible in the inbox even when the queue is
 * backed up. Inbound messages and the delivery callbacks that follow them keep
 * provider message ids deduplicated per organization.
 */
final class MessageService
{
    public function __construct(private readonly ConversationEventService $events)
    {
    }

    /**
     * @return Collection<int, Message>
     */
    public function forConversation(Organization $organization, Conversation $conversation): Collection
    {
        if ($conversation->organization_id !== $organization->id) {
            throw new ModelNotFoundException("Conversation [{$conversation->id}] was not found for this organization.");
        }

        return Message::query()
            ->where('organization_id', $organization->id)
            ->where('conversation_id', $conversation->id)
            ->with(['attachments', 'deliveryAttempts'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function inbound(
        ?User $actor,
        Organization $organization,
        Conversation $conversation,
        ?string $providerMessageId,
        ?string $body,
        ?int $senderId,
        ?MessageType $messageType = MessageType::Text,
        ?Carbon $sentAt = null,
        array $metadata = [],
    ): Message {
        return $this->store(
            $actor,
            $organization,
            $conversation,
            MessageDirection::Inbound,
            $providerMessageId,
            $body,
            $senderId,
            $messageType,
            null,
            null,
            $sentAt ?? now(),
            $metadata,
        );
    }

    public function outbound(
        ?User $actor,
        Organization $organization,
        Conversation $conversation,
        ?string $providerMessageId,
        ?string $body,
        ?int $senderId,
        ?MessageType $messageType = MessageType::Text,
    ): Message {
        return $this->store(
            $actor,
            $organization,
            $conversation,
            MessageDirection::Outbound,
            $providerMessageId,
            $body,
            $senderId,
            $messageType,
            MessageDeliveryStatus::Sent,
            null,
            now(),
            [],
        );
    }

    /**
     * Persist a queued outbound message. The caller dispatches the send job.
     */
    public function queueOutbound(
        Organization $organization,
        Conversation $conversation,
        User $actor,
        string $body,
        MessageType $messageType,
        string $idempotencyKey,
    ): Message {
        $existing = Message::query()
            ->where('organization_id', $organization->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->store(
            $actor,
            $organization,
            $conversation,
            MessageDirection::Outbound,
            null,
            $body,
            $actor->id,
            $messageType,
            MessageDeliveryStatus::Queued,
            $idempotencyKey,
            null,
            ['source' => 'communication', 'queued_at' => now()->toIso8601String()],
        );
    }

    public function markRead(User $actor, Organization $organization, Conversation $conversation): int
    {
        if ($conversation->organization_id !== $organization->id) {
            throw new ModelNotFoundException("Conversation [{$conversation->id}] was not found for this organization.");
        }

        $marked = Message::query()
            ->where('organization_id', $organization->id)
            ->where('conversation_id', $conversation->id)
            ->where('direction', MessageDirection::Inbound->value)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $conversation->unread_count = 0;
        $conversation->save();

        $this->events->record($actor, $organization, $conversation, ConversationEventService::CONVERSATION_READ, [
            'conversation_id' => $conversation->id,
            'unread_count' => 0,
        ]);

        return $marked;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Message $message): array
    {
        $message->loadMissing(['attachments', 'deliveryAttempts']);

        $lastAttempt = $message->deliveryAttempts
            ->sortByDesc('attempt_number')
            ->first();

        return [
            'id' => $message->id,
            'organization_id' => $message->organization_id,
            'conversation_id' => $message->conversation_id,
            'provider_message_id' => $message->provider_message_id,
            'direction' => $message->direction->value,
            'direction_label' => $message->direction->label(),
            'message_type' => $message->message_type->value,
            'message_type_label' => $message->message_type->label(),
            'delivery_status' => $message->delivery_status?->value,
            'delivery_status_label' => $message->delivery_status?->label(),
            'body' => $message->body,
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'sent_at' => $message->sent_at?->toIso8601String(),
            'delivered_at' => $message->delivered_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'failed_at' => $message->failed_at?->toIso8601String(),
            'failure_reason' => $message->failure_reason,
            'metadata' => $message->metadata,
            'is_retryable' => $message->isRetryable(),
            'last_error_code' => $lastAttempt?->error_code,
            'attachments' => $message->attachments
                ->map(fn (Attachment $attachment): array => [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'is_image' => $attachment->isImage(),
                    'attachment_type' => $attachment->attachment_type,
                ])
                ->values()
                ->all(),
            'created_at' => $message->created_at?->toIso8601String(),
            'updated_at' => $message->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    /**
     * @param  array<string, mixed>  $metadata
     */
    private function store(
        ?User $actor,
        Organization $organization,
        Conversation $conversation,
        MessageDirection $direction,
        ?string $providerMessageId,
        ?string $body,
        ?int $senderId,
        ?MessageType $messageType,
        ?MessageDeliveryStatus $deliveryStatus,
        ?string $idempotencyKey,
        ?Carbon $sentAt,
        array $metadata,
    ): Message {
        if ($conversation->organization_id !== $organization->id) {
            throw new ModelNotFoundException("Conversation [{$conversation->id}] was not found for this organization.");
        }

        if ($providerMessageId !== null) {
            $existing = Message::query()
                ->where('organization_id', $organization->id)
                ->where('provider_message_id', $providerMessageId)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $message = Message::query()->create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'provider_message_id' => $providerMessageId,
            'direction' => $direction->value,
            'message_type' => ($messageType ?? MessageType::Text)->value,
            'delivery_status' => $deliveryStatus?->value,
            'idempotency_key' => $idempotencyKey,
            'body' => $body,
            'sender_type' => $direction === MessageDirection::Inbound ? 'contact' : 'user',
            'sender_id' => $direction === MessageDirection::Inbound ? $senderId : ($actor !== null ? $actor->id : $senderId),
            'sent_at' => $sentAt,
            'metadata' => array_merge(['source' => 'inbox'], $metadata),
        ]);

        $conversation->last_message_at = now();
        $conversation->last_message_preview = $this->preview($message);
        $conversation->save();

        if ($direction === MessageDirection::Inbound && $conversation->status === \App\Enums\ConversationStatus::Open) {
            $conversation->unread_count = (int) $conversation->unread_count + 1;
            $conversation->save();
        }

        $event = match (true) {
            $direction === MessageDirection::Inbound => ConversationEventService::MESSAGE_RECEIVED,
            $deliveryStatus === MessageDeliveryStatus::Queued => ConversationEventService::MESSAGE_QUEUED,
            default => ConversationEventService::MESSAGE_SENT,
        };

        $this->events->record($actor, $organization, $conversation, $event, [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'provider_message_id' => $providerMessageId,
            'direction' => $direction->value,
        ]);

        return $message;
    }

    private function preview(Message $message): string
    {
        if ($message->body !== null && $message->body !== '') {
            return $message->body;
        }

        return $message->message_type->label();
    }
}
