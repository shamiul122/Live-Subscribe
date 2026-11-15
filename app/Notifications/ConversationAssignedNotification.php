<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ConversationAssignedNotification extends Notification implements ShouldQueue
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
        $contact = $this->conversation->contact;

        return [
            'type' => 'conversation_assigned',
            'title' => 'Conversation assigned to you',
            'body' => $contact !== null ? ($contact->name ?? $this->conversation->subject ?? 'A conversation was assigned to you.') : ($this->conversation->subject ?? 'A conversation was assigned to you.'),
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
