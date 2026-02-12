<?php

namespace App\Notifications;

use App\Models\MessageFlag;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MessageFlaggedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MessageFlag $flag
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
        $thread = $this->flag->thread;
        $reviewTicket = $this->flag->flagReviewTicket;

        return (new MailMessage)
            ->subject('Message Flagged for Review')
            ->line('A message has been flagged and requires review.')
            ->line('**Original Ticket:** '.$thread->subject)
            ->line('**Flagged by:** '.$this->flag->flaggedBy->name)
            ->line('**Reason:** '.$this->flag->note)
            ->action('Review Flag', url('/ready-room/tickets/'.$reviewTicket->id))
            ->line('Thank you for your service!');
    }

    /**
     * Get the Pushover representation of the notification.
     */
    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Message Flagged',
            'message' => 'A message has been flagged for review',
            'url' => url('/ready-room/tickets/'.$this->flag->flag_review_ticket_id),
        ];
    }
}
