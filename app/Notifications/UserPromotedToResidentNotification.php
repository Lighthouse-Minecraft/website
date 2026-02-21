<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserPromotedToResidentNotification extends Notification implements ShouldQueue
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
            ->subject('Welcome, Resident '.$this->user->name.'!')
            ->line('Thank you for being a great new member of the Lighthouse community!')
            ->line("You've been promoted to Resident — a full server member. We're so glad you're here and look forward to growing alongside you.")
            ->line('Keep being awesome!');
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Welcome, Resident '.$this->user->name.'!',
            'message' => "Congratulations! You've been promoted to Resident. Welcome to full server membership!",
        ];
    }
}
