<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BrigStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    /**
     * Create a notification indicating a user's brig status has been updated.
     *
     * @param  User  $user  The affected user.
     * @param  string  $summary  A human-readable summary of what changed.
     */
    public function __construct(
        public User $user,
        public string $summary,
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
            ->subject('Your Brig Status Has Been Updated')
            ->greeting('Hello, '.$this->user->name.'!')
            ->line('A staff member has updated your brig status.')
            ->line($this->summary)
            ->action('View Dashboard', route('dashboard'))
            ->line('If you have questions, you may submit a brig appeal from your dashboard.');
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Brig Status Updated',
            'message' => 'A staff member has updated your brig status: '.$this->summary,
        ];
    }
}
