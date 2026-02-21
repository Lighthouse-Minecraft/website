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

    /**
     * Create a new notification for a user released from the brig.
     *
     * @param User $user The user who has been released and will receive the notification.
     */
    public function __construct(
        public User $user
    ) {}

    /**
     * Configure which delivery channels this notification may use and set an optional Pushover key.
     *
     * @param array $channels Array of allowed channels (for example: ['mail'], ['mail', 'pushover']).
     * @param string|null $pushoverKey Optional Pushover user key to use when Pushover is enabled.
     * @return $this The notification instance for method chaining.
     */
    public function setChannels(array $channels, ?string $pushoverKey = null): self
    {
        $this->allowedChannels = $channels;
        $this->pushoverKey = $pushoverKey;

        return $this;
    }

    /**
     * Determines the delivery channels for this notification based on allowed channels and the optional pushover key.
     *
     * @return array List of channels to send the notification through; includes 'mail' if allowed, and PushoverChannel::class if 'pushover' is allowed and a pushover key is set.
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

        return $channels;
    }

    /**
     * Create a mail message notifying the recipient that their account has been released from the brig.
     *
     * @param object $notifiable The notifiable entity that will receive the notification.
     * @return \Illuminate\Notifications\Messages\MailMessage A MailMessage with subject "You Have Been Released from the Brig", explanatory lines about restored access and ranks, a welcome line, and an action button linking to the dashboard.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You Have Been Released from the Brig')
            ->line('Great news! Your Lighthouse account has been released from the Brig.')
            ->line('Your Minecraft account access has been restored and your server ranks have been re-applied.')
            ->line('Welcome back — we look forward to seeing you around!')
            ->action('Go to Dashboard', url('/dashboard'));
    }

    /**
     * Build the Pushover notification payload for a released-from-brig event.
     *
     * @return array{title: string, message: string, url: string} Payload containing `title`, `message`, and a `url` to the dashboard.
     */
    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Released from the Brig!',
            'message' => "You've been released from the Brig. Your Minecraft accounts can access the server again. Welcome back!",
            'url' => url('/dashboard'),
        ];
    }
}