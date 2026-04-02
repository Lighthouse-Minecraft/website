<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactSubmissionConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $guestName,
        public string $subject,
        public string $conversationToken
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We received your message: '.$this->subject)
            ->markdown('mail.contact-submission-confirmation', [
                'guestName' => $this->guestName,
                'subject' => $this->subject,
                'conversationToken' => $this->conversationToken,
            ]);
    }
}
