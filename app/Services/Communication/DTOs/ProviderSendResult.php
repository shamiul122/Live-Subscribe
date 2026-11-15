<?php

namespace App\Services\Communication\DTOs;

use App\Enums\CommunicationErrorCode;
use App\Enums\MessageDeliveryStatus;

/**
 * Normalized result of a provider send attempt.
 *
 * A success result is only produced after the provider returned a valid
 * acceptance response. `Delivered` is never assumed from a 2xx response.
 */
final class ProviderSendResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId = null,
        public readonly MessageDeliveryStatus $status = MessageDeliveryStatus::Sent,
        public readonly ?CommunicationErrorCode $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $retryable = false,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function accepted(string $providerMessageId, array $metadata = []): self
    {
        return new self(
            success: true,
            providerMessageId: $providerMessageId,
            status: MessageDeliveryStatus::Sent,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function acceptedWithoutId(array $metadata = []): self
    {
        return new self(success: true, status: MessageDeliveryStatus::Sent, metadata: $metadata);
    }

    public static function failure(
        CommunicationErrorCode $code,
        ?string $message = null,
        bool $retryable = false,
    ): self {
        return new self(
            success: false,
            status: MessageDeliveryStatus::Failed,
            errorCode: $code,
            errorMessage: $message ?? $code->label(),
            retryable: $retryable,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'provider_message_id' => $this->providerMessageId,
            'status' => $this->status->value,
            'error_code' => $this->errorCode?->value,
            'error_message' => $this->errorMessage,
            'retryable' => $this->retryable,
            'metadata' => $this->metadata,
        ];
    }
}
