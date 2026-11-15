<?php

namespace App\Services\Communication\DTOs;

use App\Enums\CommunicationErrorCode;

/**
 * Account-level readiness reported by an adapter before any network call.
 */
final class ProviderAccountStatus
{
    public function __construct(
        public readonly bool $configured,
        public readonly bool $usable,
        public readonly ?CommunicationErrorCode $errorCode = null,
        public readonly ?string $errorMessage = null,
    ) {
    }

    public static function ready(): self
    {
        return new self(configured: true, usable: true);
    }

    public static function failure(CommunicationErrorCode $code, string $message, bool $configured = true): self
    {
        return new self(
            configured: $configured,
            usable: false,
            errorCode: $code,
            errorMessage: $message,
        );
    }

    /**
     * @return array{configured: bool, usable: bool, error_code: string|null, error_message: string|null}
     */
    public function toArray(): array
    {
        return [
            'configured' => $this->configured,
            'usable' => $this->usable,
            'error_code' => $this->errorCode?->value,
            'error_message' => $this->errorMessage,
        ];
    }
}
