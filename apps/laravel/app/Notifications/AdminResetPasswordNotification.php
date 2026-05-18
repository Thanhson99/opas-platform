<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  string  $temporaryPassword
     * @return void
     */
    public function __construct(
        private readonly string $temporaryPassword,
    ) {}

    /**
     * Return the delivery channels for the notification.
     *
     * @param  object  $notifiable
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the temporary password email sent by an admin reset action.
     *
     * @param  object  $notifiable
     * @return MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your OPAS password has been reset')
            ->greeting('Hello!')
            ->line('An administrator reset your OPAS password.')
            ->line('Use this temporary password the next time you sign in:')
            ->line($this->temporaryPassword)
            ->line('For security, please sign in and change it as soon as possible.');
    }
}
