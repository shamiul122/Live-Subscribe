<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly OrganizationInvitation $invitation,
        public readonly string $plainToken,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organizationName = $this->invitation->organization->name;

        return (new MailMessage)
            ->subject("You have been invited to {$organizationName}")
            ->greeting('Organization invitation')
            ->line("You have been invited to join {$organizationName}.")
            ->line("Your role will be: {$this->invitation->role->label()}.")
            ->action('Accept invitation', route('invitations.show', ['token' => $this->plainToken]))
            ->line('This invitation expires on '.$this->invitation->expires_at->toDayDateTimeString().'.')
            ->line('If you did not expect this invitation, you can safely ignore this email.');
    }
}
