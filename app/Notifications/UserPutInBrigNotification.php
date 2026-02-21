<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserPutInBrigNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    /**
     * Create a notification indicating a user has been placed in the Brig.
     *
     * @param  User  $user  The affected user.
     * @param  string  $reason  The reason the user was placed in the Brig.
     * @param  \Illuminate\Support\Carbon|null  $expiresAt  Optional timestamp for when the brig placement or appeal window ends (null if no expiration / appeals available immediately).
     */
    public function __construct(
        public User $user,
        public string $reason,
        public ?\Illuminate\Support\Carbon $expiresAt = null
    ) {}

    /**
     * Set which delivery channels are allowed for this notification and an optional Pushover key.
     *
     * @param  string[]  $channels  List of allowed channel identifiers (for example `'mail'` or `'pushover'`).
     * @param  string|null  $pushoverKey  Optional Pushover key to enable Pushover channel when present.
     * @return $this The same notification instance.
     */
    public function setChannels(array $channels, ?string $pushoverKey = null): self
    {
        $this->allowedChannels = $channels;
        $this->pushoverKey = $pushoverKey;

        return $this;
    }

    /**
     * Determine which delivery channels to use for the given notifiable.
     *
     * Returns an array containing 'mail' and/or PushoverChannel::class when those channels are enabled and available.
     *
     * @param  object  $notifiable  The entity that will receive the notification.
     * @return array<string|class-string> Array of channel identifiers to deliver the notification through.
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
     * Builds the email notifying a user that their account has been placed in the Brig.
     *
     * The message includes the reason, appeal availability or a dashboard action when appeals
     * are immediately allowed, and a note that Minecraft server access is suspended.
     *
     * @return \Illuminate\Notifications\Messages\MailMessage The notification email message.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('You Have Been Placed in the Brig')
            ->line('Your Lighthouse account has been placed in the Brig by a staff member.')
            ->line('**Reason:** '.$this->reason);

        if ($this->expiresAt) {
            $mail->line('**Appeal available after:** '.$this->expiresAt->format('F j, Y \a\t g:i A T'));
            $mail->line('You will receive a notification when your appeal window opens.');
        } else {
            $mail->line('You may submit an appeal at any time via your dashboard.');
            $mail->action('Go to Dashboard', route('dashboard'));
        }

        $mail->line('Your Minecraft server access has been suspended during this period.');

        return $mail;
    }

    /**
     * Builds the Pushover payload containing the notification title and message.
     *
     * The message includes the brig reason and either the appeal availability date
     * (formatted as "M j, Y") when an expiration is set, or guidance to appeal via
     * the dashboard if no expiration is provided.
     *
     * @param  object  $notifiable  The entity to be notified (unused).
     * @return array<string,string> Associative array with keys:
     *                              - 'title': the notification title,
     *                              - 'message': the assembled message string.
     */
    public function toPushover(object $notifiable): array
    {
        $message = 'You have been placed in the Brig. Reason: '.$this->reason;

        if ($this->expiresAt) {
            $message .= ' You may appeal after '.$this->expiresAt->format('M j, Y').'.';
        } else {
            $message .= ' You may appeal at any time from your dashboard.';
        }

        return [
            'title' => 'Placed in the Brig',
            'message' => $message,
        ];
    }
}
