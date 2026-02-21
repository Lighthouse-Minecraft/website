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

    /**
     * Create a notification for a user who was promoted to Resident.
     *
     * @param  User  $user  The user that was promoted to Resident.
     */
    public function __construct(
        public User $user
    ) {}

    /**
     * Configure which channels the notification may use and optionally set a Pushover API key.
     *
     * @param  array  $channels  List of channel identifiers (e.g., 'mail', 'pushover') to allow for delivery.
     * @param  string|null  $pushoverKey  Optional Pushover API key to enable the Pushover channel when present.
     * @return $this The notification instance (fluent interface) for method chaining.
     */
    public function setChannels(array $channels, ?string $pushoverKey = null): self
    {
        $this->allowedChannels = $channels;
        $this->pushoverKey = $pushoverKey;

        return $this;
    }

    /**
     * Build the delivery channel list for this notification based on configured channels and pushover key.
     *
     * The result will include the string 'mail' when mail is allowed, and PushoverChannel::class when
     * 'pushover' is allowed and a non-null pushover key has been provided.
     *
     * @return array<string|class-string> Array of channel identifiers or channel class names to send the notification through.
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
     * Build the mail message sent to a user when they are promoted to Resident.
     *
     * @param  object  $notifiable  The notification recipient.
     * @return \Illuminate\Notifications\Messages\MailMessage Mail message with a subject welcoming the user by name and body lines announcing the Resident promotion and encouragement to engage.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome, Resident '.$this->user->name.'!')
            ->line('Thank you for being a great new member of the Lighthouse community!')
            ->line("You've been promoted to Resident — a full server member. We're so glad you're here and look forward to growing alongside you.")
            ->line('Keep being awesome!');
    }

    /**
     * Build the Pushover notification payload welcoming the promoted user.
     *
     * The returned array contains the notification fields sent to Pushover:
     * - `title`: a short title that includes the promoted user's name.
     * - `message`: the body text announcing the promotion and welcome.
     *
     * @param  object  $notifiable  The entity receiving the notification (unused).
     * @return array<string,string> Associative array with `title` and `message` keys.
     */
    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Welcome, Resident '.$this->user->name.'!',
            'message' => "Congratulations! You've been promoted to Resident. Welcome to full server membership!",
        ];
    }
}
