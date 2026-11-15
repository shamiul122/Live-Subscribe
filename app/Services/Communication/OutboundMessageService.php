<?php

namespace App\Services\Communication;

use App\Enums\CommunicationCapability;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Jobs\Communication\SendMessageJob;
use App\Models\ConnectedAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use App\Services\Communication\Attachments\AttachmentService;
use App\Services\Communication\DTOs\OutboundMessageRequest;
use App\Services\Inbox\ConversationEventService;
use App\Services\Inbox\MessageService as InboxMessageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Outbound communication pipeline.
 *
 * Queueing never performs a provider request. It validates the account and
 * capability, persists the message in the queued state, resolves attachments,
 * then dispatches a single idempotent send job.
 */
final class OutboundMessageService
{
    public function __construct(
        private readonly CommunicationProviderRegistry $providers,
        private readonly InboxMessageService $inboxMessages,
        private readonly RecipientResolver $recipients,
        private readonly AttachmentService $attachments,
        private readonly ConversationEventService $events,
    ) {
    }

    /**
     * @return array{message: Message, job_dispatched: bool}
     */
    public function queue(OutboundMessageRequest $request): array
    {
        $organization = $request->organization;
        $conversation = $request->conversation;
        $connectedAccount = $request->connectedAccount;

        if ($conversation->organization_id !== $organization->id) {
            throw ValidationException::withMessages(['message' => 'The conversation does not belong to this organization.']);
        }

        if ($connectedAccount->organization_id !== $organization->id) {
            throw ValidationException::withMessages(['message' => 'The connected account does not belong to this organization.']);
        }

        if ($conversation->connected_account_id !== $connectedAccount->id) {
            throw ValidationException::withMessages(['message' => 'The connected account is not the one used by this conversation.']);
        }

        if ($conversation->provider !== $connectedAccount->provider) {
            throw ValidationException::withMessages(['message' => 'The conversation provider does not match the connected account.']);
        }

        $body = trim($request->body);

        if ($body === '' && $request->attachmentIds === []) {
            throw ValidationException::withMessages(['body' => 'Write a message or attach a file before sending.']);
        }

        $provider = $this->providers->providerForAccount($connectedAccount);

        if ($provider === null) {
            throw ValidationException::withMessages(['message' => 'No communication adapter exists for this provider.']);
        }

        $accountStatus = $provider->accountStatus($connectedAccount);

        if (! $accountStatus->usable) {
            throw ValidationException::withMessages([
                'message' => $accountStatus->errorMessage ?? 'The connected account cannot send messages right now.',
            ]);
        }

        if (! $provider->supports(CommunicationCapability::OutboundText)) {
            throw ValidationException::withMessages([
                'message' => $provider->capabilityReason(CommunicationCapability::OutboundText) ?? 'This provider cannot send text messages.',
            ]);
        }

        if ($request->attachmentIds !== [] && ! $provider->supports(CommunicationCapability::Attachments)) {
            throw ValidationException::withMessages([
                'attachments' => $provider->capabilityReason(CommunicationCapability::Attachments) ?? 'This provider cannot send attachments.',
            ]);
        }

        // Fail before persisting if the contact has no usable provider address.
        $this->recipients->resolve($organization, $conversation, $connectedAccount);

        $idempotencyKey = $request->idempotencyKey !== null && trim($request->idempotencyKey) !== ''
            ? Str::limit(trim($request->idempotencyKey), 80, '')
            : (string) Str::uuid();

        [$message, $created] = DB::transaction(function () use ($request, $organization, $conversation, $body, $idempotencyKey): array {
            $message = $this->inboxMessages->queueOutbound(
                $organization,
                $conversation,
                $request->actor,
                $body,
                $request->messageType,
                $idempotencyKey,
            );

            $created = $message->wasRecentlyCreated;

            if ($created && $request->attachmentIds !== []) {
                $this->attachments->attachToMessage($organization, $message, $request->attachmentIds);
            }

            return [$message, $created];
        });

        if (! $created) {
            return ['message' => $message, 'job_dispatched' => false];
        }

        SendMessageJob::dispatch($message->id, $connectedAccount->id, $idempotencyKey)
            ->onQueue((string) config('communication.outbound.queue', 'default'));

        return ['message' => $message, 'job_dispatched' => true];
    }

    /**
     * Explicit retry of a failed outbound message. Jobs are not retried
     * automatically after ambiguous network failures.
     *
     * @return array{message: Message, job_dispatched: bool}
     */
    public function retry(User $actor, Organization $organization, Message $message): array
    {
        if ($message->organization_id !== $organization->id) {
            throw ValidationException::withMessages(['message' => 'The message does not belong to this organization.']);
        }

        if ($message->direction !== MessageDirection::Outbound) {
            throw ValidationException::withMessages(['message' => 'Only outbound messages can be retried.']);
        }

        if ($message->delivery_status !== MessageDeliveryStatus::Failed) {
            throw ValidationException::withMessages(['message' => 'Only failed messages can be retried.']);
        }

        $conversation = $message->conversation;
        $connectedAccount = $conversation?->connectedAccount;

        if ($conversation === null || $connectedAccount === null) {
            throw ValidationException::withMessages(['message' => 'The message has no connected conversation to retry.']);
        }

        $provider = $this->providers->providerForAccount($connectedAccount);

        if ($provider === null) {
            throw ValidationException::withMessages(['message' => 'No communication adapter exists for this provider.']);
        }

        $accountStatus = $provider->accountStatus($connectedAccount);

        if (! $accountStatus->usable) {
            throw ValidationException::withMessages([
                'message' => $accountStatus->errorMessage ?? 'The connected account cannot send messages right now.',
            ]);
        }

        $this->recipients->resolve($organization, $conversation, $connectedAccount);

        $idempotencyKey = $message->idempotency_key ?? (string) Str::uuid();

        $message->delivery_status = MessageDeliveryStatus::Queued;
        $message->failed_at = null;
        $message->failure_reason = null;
        $message->sent_at = null;
        $message->idempotency_key = $idempotencyKey;
        $message->save();

        $this->events->record($actor, $organization, $conversation, ConversationEventService::MESSAGE_QUEUED, [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'retry' => true,
        ]);

        SendMessageJob::dispatch($message->id, $connectedAccount->id, $idempotencyKey)
            ->onQueue((string) config('communication.outbound.queue', 'default'));

        return ['message' => $message, 'job_dispatched' => true];
    }
}
