<?php

namespace App\Services\Inbox;

use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Organization;
use App\Models\User;

/**
 * Append-only conversation lifecycle event recorder.
 *
 * Events are written for status changes, assignments, reads and message
 * activity. Event payloads never contain credentials or provider secrets.
 */
final class ConversationEventService
{
    public const CONVERSATION_CREATED = 'conversation_created';
    public const CONVERSATION_ASSIGNED = 'conversation_assigned';
    public const STATUS_CHANGED = 'conversation_status_changed';
    public const PRIORITY_CHANGED = 'conversation_priority_changed';
    public const MESSAGE_RECEIVED = 'message_received';
    public const MESSAGE_QUEUED = 'message_queued';
    public const MESSAGE_SENT = 'message_sent';
    public const MESSAGE_FAILED = 'message_failed';
    public const MESSAGE_DELIVERED = 'message_delivered';
    public const CONVERSATION_READ = 'conversation_read';
    public const CONVERSATION_CLOSED = 'conversation_closed';

    /**
     * @param  array<string, mixed>  $eventData
     */
    public function record(?User $actor, Organization $organization, Conversation $conversation, string $eventType, array $eventData = []): ConversationEvent
    {
        return ConversationEvent::query()->create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'event_type' => $eventType,
            'actor_id' => $actor?->id,
            'event_data' => $eventData,
            'created_at' => now(),
        ]);
    }
}
