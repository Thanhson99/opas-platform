<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QueuedVerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return void
     */
    public function __construct(
        private readonly string $code,
        private readonly int $expireMinutes,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the verification code email for the registration flow.
     *
     * @param  object  $notifiable
     * @return MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your OPAS verification code')
            ->greeting('Hello!')
            ->line('Use the verification code below to activate your OPAS account.')
            ->line($this->code)
            ->line(sprintf('This code will expire in %d minutes.', $this->expireMinutes))
            ->line('If you did not create this account, you can ignore this email.');
    }
}
