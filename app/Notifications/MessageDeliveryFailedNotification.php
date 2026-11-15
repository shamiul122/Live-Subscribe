<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class MessageDeliveryFailedNotification extends Notification implements ShouldQueue
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
            'type' => 'delivery_failed',
            'title' => 'Message could not be sent',
            'body' => Str::limit((string) ($this->message->failure_reason ?? 'The provider rejected the message.'), 160, '…'),
            'message_id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'organization_id' => $this->message->organization_id,
            'organization_slug' => $organization?->slug,
            'url' => $organization !== null && $conversation !== null
                ? route('organizations.inbox.show', ['organization' => $organization->slug, 'conversation' => $conversation->id], false)
                : null,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
