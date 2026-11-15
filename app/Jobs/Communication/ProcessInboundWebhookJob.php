<?php

namespace App\Jobs\Communication;

use App\Models\WebhookEvent;
use App\Services\Communication\Inbound\InboundMessageProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Processes one stored webhook event from its sanitized payload snapshot.
 *
 * A single attempt is used; failures are recorded on the event and can be
 * inspected/replayed by an operator. Payloads and tokens are never logged.
 */
class ProcessInboundWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $webhookEventId)
    {
    }

    public function handle(InboundMessageProcessor $processor): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        if ($event === null || $event->processing_status === 'processed') {
            return;
        }

        $event->attempts = $event->attempts + 1;
        $event->processing_status = 'processing';
        $event->failure_reason = null;
        $event->save();

        try {
            $processor->process($event);

            $event->processing_status = 'processed';
            $event->failure_reason = null;
            $event->processed_at = now();
            $event->save();
        } catch (Throwable $exception) {
            $event->processing_status = 'failed';
            $event->failure_reason = Str::limit($exception->getMessage() !== '' ? $exception->getMessage() : $exception::class, 255, '');
            $event->processed_at = now();
            $event->save();

            Log::warning('Inbound webhook processing failed', [
                'webhook_event_id' => $event->id,
                'provider' => $event->provider,
                'reason' => $event->failure_reason,
            ]);
        }
    }
}
