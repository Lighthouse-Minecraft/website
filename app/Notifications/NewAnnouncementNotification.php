<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Notifications\Channels\DiscordChannel;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public Announcement $announcement
    ) {}

    public function setChannels(array $channels, ?string $pushoverKey = null): self
    {
        $this->allowedChannels = $channels;
        $this->pushoverKey = $pushoverKey;

        return $this;
    }

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

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Announcement: '.$this->announcement->title)
            ->line('A new announcement has been posted:')
            ->line('**'.$this->announcement->title.'**')
            ->line('By '.$this->announcement->authorName())
            ->action('View on Dashboard', route('dashboard'));
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'New Announcement',
            'message' => $this->announcement->title,
            'url' => route('dashboard'),
        ];
    }

    public function toDiscord(object $notifiable): string
    {
        return "**New Announcement:** {$this->announcement->title}\n**By:** {$this->announcement->authorName()}\n".route('dashboard');
    }
}
