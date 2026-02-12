<?php

namespace App\Notifications;

use App\Models\Thread;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification implements ShouldQueue
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
        $channels = ['mail'];

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
        $message = (new MailMessage)
            ->subject('Ticket Assigned: '.$this->thread->subject)
            ->line('A ticket has been assigned to you.')
            ->line('**Subject:** '.$this->thread->subject)
            ->line('**Department:** '.$this->thread->department->label());

        if ($this->thread->assignedTo) {
            $message->line('**Assigned to:** '.$this->thread->assignedTo->name);
        }

        return $message
            ->action('View Ticket', url('/ready-room/tickets/'.$this->thread->id))
            ->line('Thank you for your service!');
    }

    /**
     * Get the Pushover representation of the notification.
     */
    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Ticket Assigned',
            'message' => $this->thread->subject,
            'url' => url('/ready-room/tickets/'.$this->thread->id),
        ];
    }
}
