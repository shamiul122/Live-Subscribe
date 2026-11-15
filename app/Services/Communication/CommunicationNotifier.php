<?php

namespace App\Services\Communication;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ConversationAssignedNotification;
use App\Notifications\ConversationStatusChangedNotification;
use App\Notifications\MessageDeliveryFailedNotification;
use App\Notifications\NewInboundMessageNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Internal (database) notification fan-out for communication events.
 *
 * Notifications are informational only. They never contain provider tokens and
 * can be disabled through config/communication.php.
 */
final class CommunicationNotifier
{
    public function notifyInbound(Message $message): void
    {
        if (! $this->enabled()) {
            return;
        }

        $organization = $message->organization;

        if ($organization === null) {
            return;
        }

        $recipients = $this->members($organization);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new NewInboundMessageNotification($message));
        }
    }

    public function notifyDeliveryFailure(Message $message): void
    {
        if (! $this->enabled()) {
            return;
        }

        $organization = $message->organization;
        $conversation = $message->conversation;

        if ($organization === null) {
            return;
        }

        $recipients = $this->audience($organization, $conversation);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new MessageDeliveryFailedNotification($message));
        }
    }

    public function notifyConversationAssigned(Conversation $conversation, ?User $actor = null): void
    {
        if (! $this->enabled() || $conversation->assigned_to_id === null) {
            return;
        }

        if ($actor !== null && $actor->id === $conversation->assigned_to_id) {
            return;
        }

        $assignee = User::query()->find($conversation->assigned_to_id);

        if ($assignee !== null) {
            Notification::send($assignee, new ConversationAssignedNotification($conversation));
        }
    }

    public function notifyConversationStatusChanged(Conversation $conversation, ?User $actor = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $organization = $conversation->organization;

        if ($organization === null) {
            return;
        }

        $recipients = $this->audience($organization, $conversation)
            ->reject(fn (User $user): bool => $actor !== null && $user->id === $actor->id);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ConversationStatusChangedNotification($conversation));
        }
    }

    private function enabled(): bool
    {
        return (bool) config('communication.notifications.enabled', true);
    }

    /**
     * @return Collection<int, User>
     */
    private function members(Organization $organization): Collection
    {
        /** @var Collection<int, User> $members */
        $members = $organization->members()->get();
        $owner = $organization->owner;

        if ($owner instanceof User && ! $members->contains(fn (User $user): bool => $user->id === $owner->id)) {
            $members->push($owner);
        }

        return $members;
    }

    /**
     * Prefer the assignee; otherwise notify active members.
     *
     * @return Collection<int, User>
     */
    private function audience(Organization $organization, ?Conversation $conversation): Collection
    {
        if ($conversation !== null && $conversation->assigned_to_id !== null) {
            $assignee = User::query()->find($conversation->assigned_to_id);

            if ($assignee !== null) {
                return new Collection([$assignee]);
            }
        }

        return $this->members($organization);
    }
}
