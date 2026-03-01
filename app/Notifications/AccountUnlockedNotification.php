<?php

namespace App\Notifications;

use App\Notifications\Channels\DiscordChannel;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class AccountUnlockedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function setChannels(array $channels, ?string $pushoverKey = null): self
    {
        $this->allowedChannels = $channels;
        $this->pushoverKey = $pushoverKey;

        return $this;
    }

    public function getPushoverKey(): ?string
    {
        return $this->pushoverKey;
    }

    public function via(object $notifiable): array
    {
        $channels = [];

        if (in_array('mail', $this->allowedChannels, true)) {
            $channels[] = 'mail';
        }

        if (in_array('pushover', $this->allowedChannels, true) && $this->pushoverKey) {
            $channels[] = PushoverChannel::class;
        }

        if (in_array('discord', $this->allowedChannels, true)) {
            $channels[] = DiscordChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Lighthouse Account Has Been Unlocked!')
            ->line('Great news! Your Lighthouse Minecraft account has been unlocked.')
            ->line('You can now log in and access the community.')
            ->action('Go to Dashboard', route('dashboard'));
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Account Unlocked!',
            'message' => 'Your Lighthouse account has been unlocked. You can now access the community!',
            'url' => route('dashboard'),
        ];
    }

    public function toDiscord(object $notifiable): string
    {
        return "**Account Unlocked!**\nYour Lighthouse account has been unlocked. You can now access the community!\n".route('dashboard');
    }
}
