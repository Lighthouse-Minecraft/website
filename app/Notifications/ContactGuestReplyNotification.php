<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\Thread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactGuestReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Thread $thread,
        public Message $message
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Re: '.$this->thread->subject)
            ->markdown('mail.contact-guest-reply', [
                'thread' => $this->thread,
                'message' => $this->message,
                'conversationUrl' => url('/contact/thread/'.$this->thread->conversation_token),
            ]);
    }
}
