<?php

namespace App\Notifications;

use App\Models\Thread;
use App\Notifications\Channels\DiscordChannel;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewTicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public Thread $thread
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

    public function toDiscord(object $notifiable): string
    {
        return "**New Ticket:** {$this->thread->subject}\n**Department:** {$this->thread->department->label()}\n**From:** {$this->thread->createdBy->name}\n".url('/tickets/'.$this->thread->id);
    }
}
