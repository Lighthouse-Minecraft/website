<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BrigTimerExpiredNotification extends Notification implements ShouldQueue
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
            ->subject('Your Brig Period Has Ended — You May Now Appeal')
            ->line('Your mandatory brig period has ended.')
            ->line('You can now submit an appeal to have your account reviewed by staff.')
            ->line('Please visit your dashboard and use the appeal button to submit your case.')
            ->action('Go to Dashboard', url('/dashboard'))
            ->line('Note: submitting an appeal does not guarantee reinstatement.');
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Brig Period Ended',
            'message' => 'Your brig period has ended. You may now submit an appeal via your dashboard.',
            'url' => url('/dashboard'),
        ];
    }
}
