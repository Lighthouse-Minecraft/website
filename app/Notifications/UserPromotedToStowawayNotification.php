<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserPromotedToStowawayNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public User $newStowaway
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
            ->subject('New Stowaway User: '.$this->newStowaway->name)
            ->line($this->newStowaway->name.' has agreed to the rules and is awaiting Stowaway review.')
            ->line('Please review their profile and promote or manage their account as appropriate.')
            ->action('View Profile', url(route('profile.show', $this->newStowaway)))
            ->line('Thank you for your service!');
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'New Stowaway: '.$this->newStowaway->name,
            'message' => $this->newStowaway->name.' has agreed to the rules and is awaiting review.',
            'url' => url(route('profile.show', $this->newStowaway)),
        ];
    }
}
