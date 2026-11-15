<?php

namespace App\Contracts\Communication;

use App\Enums\CommunicationCapability;
use App\Enums\SocialProvider;
use App\Models\Attachment;
use App\Models\ConnectedAccount;
use App\Services\Communication\DTOs\DeliveryStatusData;
use App\Services\Communication\DTOs\InboundMessageData;
use App\Services\Communication\DTOs\ProviderAccountStatus;
use App\Services\Communication\DTOs\ProviderSendResult;
use App\Services\Communication\DTOs\WebhookVerificationResult;

/**
 * Provider-agnostic communication adapter contract.
 *
 * Account authorization and token lifecycle remain the responsibility of the
 * Step 4 SocialAccount providers. This contract covers message communication
 * only, and every adapter must truthfully declare what it can do.
 */
interface CommunicationProviderInterface
{
    public function identifier(): string;

    public function provider(): SocialProvider;

    /**
     * Whether the provider application is configured at all (global config).
     */
    public function isConfigured(): bool;

    /**
     * Whether this specific connected account can be used right now.
     */
    public function accountStatus(ConnectedAccount $account): ProviderAccountStatus;

    public function supports(CommunicationCapability $capability): bool;

    /**
     * Human readable reason a capability is unavailable, when known.
     */
    public function capabilityReason(CommunicationCapability $capability): ?string;

    /**
     * @return array<string, bool>
     */
    public function capabilities(): array;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers  Lower-cased header names.
     * @param  array<string, mixed>  $context
     */
    public function verifyWebhook(string $method, array $payload, array $headers, array $context = []): WebhookVerificationResult;

    /**
     * Resolve the connected account a webhook belongs to without trusting any
     * organization identifier in the payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $context
     */
    public function resolveAccount(array $payload, array $headers, array $context = []): ?ConnectedAccount;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<int, InboundMessageData>
     */
    public function normalizeInbound(array $payload, ConnectedAccount $account, array $context = []): array;

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendText(ConnectedAccount $account, string $recipient, string $body, array $context = []): ProviderSendResult;

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendAttachment(ConnectedAccount $account, string $recipient, Attachment $attachment, ?string $caption = null, array $context = []): ProviderSendResult;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, DeliveryStatusData>
     */
    public function normalizeDeliveryStatus(array $payload, ConnectedAccount $account): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function providerEventId(array $payload): ?string;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function eventType(array $payload): string;
}
