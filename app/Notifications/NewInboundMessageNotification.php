<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Internal database notification for a newly received inbox message.
 *
 * The notification never contains provider credentials or the full raw
 * webhook payload.
 */
class NewInboundMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Message $message)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $conversation = $this->message->conversation;
        $organization = $this->message->organization;

        return [
            'type' => 'inbound_message',
            'title' => 'New message',
            'body' => Str::limit((string) ($this->message->body ?? $this->message->message_type->label()), 160, '…'),
            'message_id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'organization_id' => $this->message->organization_id,
            'organization_slug' => $organization?->slug,
            'contact_name' => $conversation?->contact?->name,
            'url' => $organization !== null && $conversation !== null
                ? route('organizations.inbox.show', ['organization' => $organization->slug, 'conversation' => $conversation->id], false)
                : null,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
