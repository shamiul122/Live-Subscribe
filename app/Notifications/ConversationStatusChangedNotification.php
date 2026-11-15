<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ConversationStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Conversation $conversation)
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
        $organization = $this->conversation->organization;

        return [
            'type' => 'conversation_status_changed',
            'title' => 'Conversation status changed',
            'body' => 'The conversation is now '.$this->conversation->status->label().'.',
            'conversation_id' => $this->conversation->id,
            'organization_id' => $this->conversation->organization_id,
            'organization_slug' => $organization?->slug,
            'url' => $organization !== null
                ? route('organizations.inbox.show', ['organization' => $organization->slug, 'conversation' => $this->conversation->id], false)
                : null,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
