<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\DiscordChannel;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserPromotedToCitizenNotification extends Notification implements ShouldQueue
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

        if (in_array('discord', $this->allowedChannels)) {
            $channels[] = DiscordChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Congratulations, Citizen '.$this->user->name.'!')
            ->markdown('mail.user-promoted-citizen');
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Congratulations, Citizen '.$this->user->name.'!',
            'message' => "You've been promoted to Citizen — our highest membership level. Thank you for being a cornerstone of the Lighthouse community!",
        ];
    }

    public function toDiscord(object $notifiable): string
    {
        return "**Congratulations, Citizen {$this->user->name}!**\nYou've been promoted to Citizen — our highest membership level. Thank you for being a cornerstone of the Lighthouse community!";
    }
}
