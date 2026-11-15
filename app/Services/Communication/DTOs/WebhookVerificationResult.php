<?php

namespace App\Services\Communication\DTOs;

/**
 * Result of verifying an inbound webhook request.
 */
final class WebhookVerificationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $challenge = null,
        public readonly ?string $reason = null,
    ) {
    }

    public static function valid(?string $challenge = null): self
    {
        return new self(valid: true, challenge: $challenge);
    }

    public static function invalid(string $reason): self
    {
        return new self(valid: false, reason: $reason);
    }
}
