<?php

namespace App\Services\Communication\Webhooks;

use App\Jobs\Communication\ProcessInboundWebhookJob;
use App\Models\ConnectedAccount;
use App\Models\WebhookEvent;
use App\Services\Communication\CommunicationProviderRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provider-independent webhook entry point.
 *
 * Responsibilities: adapter resolution, signature/challenge verification,
 * connected-account resolution, payload redaction, deduplication and queueing.
 * It never trusts an organization id from the request.
 */
final class WebhookManager
{
    public function __construct(private readonly CommunicationProviderRegistry $providers)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers  Lower-cased header names.
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>|string}
     */
    public function handle(
        string $providerKey,
        string $method,
        array $payload,
        array $headers,
        ?string $rawBody,
        array $query = [],
    ): array {
        $provider = $this->providers->provider($providerKey);

        if ($provider === null) {
            return ['status' => 404, 'body' => ['error' => 'unknown_provider']];
        }

        $method = strtolower($method);

        if ($method === 'get') {
            $result = $provider->verifyWebhook('get', $payload, $headers);

            if (! $result->valid) {
                return ['status' => 403, 'body' => ['error' => $result->reason ?? 'invalid_verification']];
            }

            return ['status' => 200, 'body' => (string) ($result->challenge ?? '')];
        }

        $maxBytes = (int) config('communication.webhooks.max_payload_bytes', 262144);

        if (is_string($rawBody) && strlen($rawBody) > $maxBytes) {
            return ['status' => 413, 'body' => ['error' => 'payload_too_large']];
        }

        // Telegram subscriptions use `?account=`, while the generic hook accepts
        // the explicit `connected_account_id` name.
        $connectedAccountId = $query['account'] ?? $query['connected_account_id'] ?? null;
        $context = [
            'raw_body' => $rawBody,
            'connected_account_id' => $connectedAccountId,
        ];

        $account = $provider->resolveAccount($payload, $headers, $context);
        $context['account'] = $account;

        $result = $provider->verifyWebhook('post', $payload, $headers, $context);

        if (! $result->valid) {
            return ['status' => 403, 'body' => ['error' => $result->reason ?? 'invalid_signature']];
        }

        if ($account === null && $providerKey === 'telegram') {
            // Telegram subscriptions are per bot account; an unresolvable
            // account must never fall back to an organization guess.
            return ['status' => 403, 'body' => ['error' => 'account_required']];
        }

        $encoded = $rawBody !== null && $rawBody !== '' ? $rawBody : $this->canonicalJson($payload);
        $payloadHash = hash('sha256', $encoded);

        $existing = WebhookEvent::query()
            ->where('provider', $providerKey)
            ->where('payload_hash', $payloadHash)
            ->first();

        if ($existing !== null) {
            return ['status' => 200, 'body' => ['status' => 'duplicate']];
        }

        $sanitized = $this->sanitize($payload);
        $eventType = $provider->eventType($payload);
        $providerEventId = $provider->providerEventId($payload);

        try {
            $event = DB::transaction(fn (): WebhookEvent => WebhookEvent::query()->create([
                'provider' => $providerKey,
                'connected_account_id' => $account?->id,
                'provider_event_id' => $providerEventId !== null ? Str::limit($providerEventId, 160, '') : null,
                'event_type' => $eventType,
                'processing_status' => $account === null ? 'ignored' : 'pending',
                'attempts' => 0,
                'received_at' => now(),
                'processed_at' => $account === null ? now() : null,
                'failure_reason' => $account === null ? 'connected_account_not_found' : null,
                'payload_hash' => $payloadHash,
                'payload' => is_array($sanitized) ? $sanitized : [],
            ]));
        } catch (QueryException) {
            // A concurrent delivery stored the same payload first.
            $duplicate = WebhookEvent::query()
                ->where('provider', $providerKey)
                ->where('payload_hash', $payloadHash)
                ->exists();

            if ($duplicate) {
                return ['status' => 200, 'body' => ['status' => 'duplicate']];
            }

            return ['status' => 500, 'body' => ['error' => 'webhook_persist_failed']];
        }

        if ($account instanceof ConnectedAccount) {
            ProcessInboundWebhookJob::dispatch($event->id)
                ->onQueue((string) config('communication.outbound.queue', 'default'));
        }

        return ['status' => 200, 'body' => ['status' => $account === null ? 'ignored' : 'accepted']];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function canonicalJson(array $payload): string
    {
        $encoded = json_encode($payload);

        return $encoded === false ? '' : $encoded;
    }

    /**
     * Remove credential-like values before persisting a provider payload.
     */
    private function sanitize(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 12) {
            return '[truncated]';
        }

        if (is_array($value)) {
            $clean = [];

            foreach ($value as $key => $item) {
                if (is_string($key) && $this->isSensitiveKey($key)) {
                    $clean[$key] = '[redacted]';

                    continue;
                }

                $clean[$key] = $this->sanitize($item, $depth + 1);
            }

            return $clean;
        }

        if (is_string($value) && strlen($value) > 5000) {
            return Str::limit($value, 5000, '…');
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (['token', 'secret', 'password', 'authorization', 'signature'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
