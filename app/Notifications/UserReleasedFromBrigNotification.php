<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserReleasedFromBrigNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public User $user
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

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You Have Been Released from the Brig')
            ->line('Great news! Your Lighthouse account has been released from the Brig.')
            ->line('Your Minecraft account access has been restored and your server ranks have been re-applied.')
            ->line('Welcome back — we look forward to seeing you around!')
            ->action('Go to Dashboard', url('/dashboard'));
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Released from the Brig!',
            'message' => "You've been released from the Brig. Your Minecraft accounts can access the server again. Welcome back!",
            'url' => url('/dashboard'),
        ];
    }
}
