<?php

namespace App\Notifications;

use App\Models\Message;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewTicketReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Message $message
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        // Check if email is allowed (set by TicketNotificationService)
        if (isset($notifiable->_notification_email_allowed) && $notifiable->_notification_email_allowed) {
            $channels[] = 'mail';
        }

        // Check if Pushover is allowed (set by TicketNotificationService)
        if (isset($notifiable->_notification_pushover_allowed) && $notifiable->_notification_pushover_allowed && $notifiable->pushover_key) {
            $channels[] = PushoverChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $thread = $this->message->thread;

        return (new MailMessage)
            ->subject('New Reply: '.$thread->subject)
            ->line('There is a new reply on a ticket you\'re following.')
            ->line('**Subject:** '.$thread->subject)
            ->line('**From:** '.$this->message->user->name)
            ->line('**Message:** '.Str::limit($this->message->body, 100))
            ->action('View Ticket', url('/tickets/'.$thread->id))
            ->line('Thank you for your service!');
    }

    /**
     * Get the Pushover representation of the notification.
     */
    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'New Reply',
            'message' => Str::limit($this->message->body, 100),
            'url' => url('/tickets/'.$this->message->thread_id),
        ];
    }
}
