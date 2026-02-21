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

    / **
     * Create a new notification for the given user.
     *
     * @param User $user The user associated with this notification.
     */
    public function __construct(
        public User $user
    ) {}

    /**
     * Configure which delivery channels the notification may use.
     *
     * Optionally provides a Pushover API key to enable the Pushover channel when requested.
     *
     * @param array $channels Array of channel identifiers (e.g., 'mail', 'pushover').
     * @param string|null $pushoverKey Pushover API key to enable Pushover delivery, or null to leave disabled.
     * @return $this The notification instance for method chaining.
     */
    public function setChannels(array $channels, ?string $pushoverKey = null): self
    {
        $this->allowedChannels = $channels;
        $this->pushoverKey = $pushoverKey;

        return $this;
    }

    /**
     * Determine which channels should be used to deliver this notification for the given notifiable.
     *
     * @param object $notifiable The entity that will receive the notification.
     * @return array Array of channel identifiers: contains `'mail'` when mail delivery is allowed, and `PushoverChannel::class` when `'pushover'` is allowed and a pushover key has been provided.
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
     * Create the mail message notifying a user that their brig period has ended and they may submit an appeal.
     *
     * @return \Illuminate\Notifications\Messages\MailMessage A MailMessage with subject "Your Brig Period Has Ended — You May Now Appeal", body lines informing the recipient the brig period ended and instructing them to submit an appeal via the dashboard, an action button linking to /dashboard, and a final note that submitting an appeal does not guarantee reinstatement.
     */
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

    /**
     * Build the Pushover payload for this notification.
     *
     * @param object $notifiable The entity receiving the notification.
     * @return array The Pushover payload containing:
     *               - 'title': notification title,
     *               - 'message': body text,
     *               - 'url': link to the user's dashboard.
     */
    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Brig Period Ended',
            'message' => 'Your brig period has ended. You may now submit an appeal via your dashboard.',
            'url' => url('/dashboard'),
        ];
    }
}