<?php

namespace App\Notifications;

use App\Models\MessageFlag;
use App\Notifications\Channels\DiscordChannel;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MessageFlaggedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public MessageFlag $flag
    ) {}

    /**
     * Set which channels are allowed for this notification
     */
    public function setChannels(array $channels, ?string $pushoverKey = null): self
    {
        $this->allowedChannels = $channels;
        $this->pushoverKey = $pushoverKey;

        return $this;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (in_array('mail', $this->allowedChannels)) {
            $channels[] = 'mail';
        }

        if (in_array('pushover', $this->allowedChannels) && $this->pushoverKey) {
            $channels[] = PushoverChannel::class;
        }

        if (in_array('discord', $this->allowedChannels)) {
            $channels[] = DiscordChannel::class;
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
            ->action('Review Flag', url('/tickets/'.$reviewTicket->id))
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
            'url' => url('/tickets/'.$this->flag->flag_review_ticket_id),
        ];
    }

    public function toDiscord(object $notifiable): string
    {
        return "**Message Flagged for Review**\n**Flagged by:** {$this->flag->flaggedBy->name}\n**Reason:** {$this->flag->note}\n".url('/tickets/'.$this->flag->flag_review_ticket_id);
    }
}
