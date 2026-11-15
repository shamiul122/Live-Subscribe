<?php

namespace App\Services\Communication\DTOs;

use App\Enums\MessageType;
use App\Models\ConnectedAccount;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;

/**
 * Validated request to queue an outbound message.
 */
final class OutboundMessageRequest
{
    /**
     * @param  array<int, int>  $attachmentIds
     */
    public function __construct(
        public readonly Organization $organization,
        public readonly Conversation $conversation,
        public readonly ConnectedAccount $connectedAccount,
        public readonly User $actor,
        public readonly string $body,
        public readonly MessageType $messageType = MessageType::Text,
        public readonly array $attachmentIds = [],
        public readonly ?string $idempotencyKey = null,
    ) {
    }
}
