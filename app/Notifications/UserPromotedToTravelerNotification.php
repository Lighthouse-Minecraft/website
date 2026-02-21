<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserPromotedToTravelerNotification extends Notification implements ShouldQueue
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
            ->subject('Welcome to Traveler Status!')
            ->line('Congratulations, '.$this->user->name.'!')
            ->line("You've been promoted to Traveler in the Lighthouse community.")
            ->line('You can now set up your Minecraft account to join the server. Head to your settings to get started!')
            ->action('Set Up Minecraft Account', url(route('settings.minecraft-accounts')))
            ->line("We're glad to have you aboard!");
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Welcome to Traveler Status!',
            'message' => "You've been promoted to Traveler! Set up your Minecraft account to join the server.",
            'url' => url(route('settings.minecraft-accounts')),
        ];
    }
}
