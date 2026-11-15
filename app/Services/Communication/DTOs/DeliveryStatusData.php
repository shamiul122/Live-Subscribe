<?php

namespace App\Services\Communication\DTOs;

use App\Enums\MessageDeliveryStatus;
use Illuminate\Support\Carbon;

/**
 * Normalized provider delivery/read/failure callback.
 */
final class DeliveryStatusData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $providerMessageId,
        public readonly MessageDeliveryStatus $status,
        public readonly ?Carbon $occurredAt = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
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
            'status' => $this->status->value,
            'occurred_at' => $this->occurredAt?->toIso8601String(),
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'metadata' => $this->metadata,
        ];
    }
}
