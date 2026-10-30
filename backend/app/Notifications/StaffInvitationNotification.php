<?php

namespace App\Notifications;

use App\Models\StaffInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly StaffInvitation $invitation,
        private readonly string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $url = $frontend.'/accept-invitation#token='.rawurlencode($this->token);

        return (new MailMessage)
            ->subject('You have been invited to '.($this->invitation->company?->name ?? config('app.name')))
            ->greeting('You have been invited')
            ->line('A company administrator invited you to join their Construction ERP workspace.')
            ->line('This invitation expires in '.config('invitations.expires_hours', 72).' hours and can be used once.')
            ->action('Accept invitation', $url)
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }
}
