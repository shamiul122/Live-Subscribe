<?php

namespace App\Support\Billing;

/**
 * Normalized provider event. Mock payments never emit these, but future
 * providers will map webhook payloads into this shape so subscription logic
 * does not depend on a provider's schema.
 */
final class GatewayEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $eventId,
        public readonly string $type,
        public readonly ?string $providerReference,
        public readonly array $payload = [],
    ) {
    }
}
