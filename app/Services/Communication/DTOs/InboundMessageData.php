<?php

namespace App\Services\Communication\DTOs;

use App\Enums\MessageType;
use Illuminate\Support\Carbon;

/**
 * Normalized inbound message extracted from a provider webhook payload.
 *
 * Only the data needed to create an inbox message is retained. Raw provider
 * payloads are not stored here so tokens and unrelated personal data cannot
 * leak through message metadata.
 */
final class InboundMessageData
{
    /**
     * @param  array<int, array<string, mixed>>  $attachments
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $providerMessageId,
        public readonly ?string $body = null,
        public readonly ?string $senderId = null,
        public readonly ?string $senderName = null,
        public readonly ?string $senderEmail = null,
        public readonly ?string $senderPhone = null,
        public readonly ?string $providerConversationId = null,
        public readonly MessageType $messageType = MessageType::Text,
        public readonly ?Carbon $sentAt = null,
        public readonly array $attachments = [],
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_message_id' => $this->providerMessageId,
            'body' => $this->body,
            'sender_id' => $this->senderId,
            'sender_name' => $this->senderName,
            'sender_email' => $this->senderEmail,
            'sender_phone' => $this->senderPhone,
            'provider_conversation_id' => $this->providerConversationId,
            'message_type' => $this->messageType->value,
            'sent_at' => $this->sentAt?->toIso8601String(),
            'attachments' => $this->attachments,
            'metadata' => $this->metadata,
        ];
    }
}
