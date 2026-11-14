<?php

namespace App\Services\Inbox;

use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Stores inbound and outbound messages inside an organization-scoped
 * conversation.
 *
 * Provider integrations are intentionally not wired here yet; this service only
 * records internal messages and keeps provider message IDs deduplicated.
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
            ->orderBy('created_at')
            ->get();
    }

    public function inbound(
        User $actor,
        Organization $organization,
        Conversation $conversation,
        ?string $providerMessageId,
        string $body,
        ?int $senderId,
        ?MessageType $messageType = MessageType::Text,
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
        );
    }

    public function outbound(
        User $actor,
        Organization $organization,
        Conversation $conversation,
        ?string $providerMessageId,
        string $body,
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
        return [
            'id' => $message->id,
            'organization_id' => $message->organization_id,
            'conversation_id' => $message->conversation_id,
            'provider_message_id' => $message->provider_message_id,
            'direction' => $message->direction->value,
            'direction_label' => $message->direction->label(),
            'message_type' => $message->message_type->value,
            'message_type_label' => $message->message_type->label(),
            'body' => $message->body,
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'sent_at' => $message->sent_at?->toIso8601String(),
            'delivered_at' => $message->delivered_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'failed_at' => $message->failed_at?->toIso8601String(),
            'failure_reason' => $message->failure_reason,
            'metadata' => $message->metadata,
            'created_at' => $message->created_at?->toIso8601String(),
            'updated_at' => $message->updated_at?->toIso8601String(),
        ];
    }

    private function store(
        User $actor,
        Organization $organization,
        Conversation $conversation,
        MessageDirection $direction,
        ?string $providerMessageId,
        string $body,
        ?int $senderId,
        ?MessageType $messageType,
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
            'body' => $body,
            'sender_type' => $direction === MessageDirection::Inbound ? 'contact' : 'user',
            'sender_id' => $direction === MessageDirection::Inbound ? $senderId : $actor->id,
            'sent_at' => now(),
            'metadata' => [
                'source' => 'inbox',
            ],
        ]);

        $conversation->last_message_at = now();
        $conversation->last_message_preview = $this->preview($message);
        $conversation->save();

        if ($direction === MessageDirection::Inbound && $conversation->status === \App\Enums\ConversationStatus::Open) {
            $conversation->unread_count = (int) $conversation->unread_count + 1;
            $conversation->save();
        }

        $this->events->record(
            $actor,
            $organization,
            $conversation,
            $direction === MessageDirection::Inbound ? ConversationEventService::MESSAGE_RECEIVED : ConversationEventService::MESSAGE_SENT,
            [
                'conversation_id' => $conversation->id,
                'provider_message_id' => $providerMessageId,
                'direction' => $direction->value,
            ],
        );

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
