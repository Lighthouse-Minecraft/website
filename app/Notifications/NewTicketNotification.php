<?php

namespace App\Notifications;

use App\Models\Thread;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewTicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Thread $thread
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        // Email handled by TicketNotificationService logic
        $channels[] = 'mail';

        // Pushover handled by TicketNotificationService logic
        if ($notifiable->pushover_key) {
            $channels[] = PushoverChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Ticket: '.$this->thread->subject)
            ->line('A new ticket has been created in your department.')
            ->line('**Subject:** '.$this->thread->subject)
            ->line('**Department:** '.$this->thread->department->label())
            ->line('**From:** '.$this->thread->createdBy->name)
            ->action('View Ticket', url('/tickets/'.$this->thread->id))
            ->line('Thank you for your service!');
    }

    /**
     * Get the Pushover representation of the notification.
     */
    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'New Ticket',
            'message' => $this->thread->subject,
            'url' => url('/tickets/'.$this->thread->id),
        ];
    }
}
