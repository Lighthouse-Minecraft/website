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

    /**
     * Create a new notification instance for a promoted user.
     *
     * @param  User  $user  The user who was promoted to Traveler.
     */
    public function __construct(
        public User $user
    ) {}

    /**
     * Configure which delivery channels the notification may use and optionally set a Pushover key.
     *
     * @param  array  $channels  Array of channel identifiers to allow (e.g. ['mail'], ['mail','pushover']).
     * @param  string|null  $pushoverKey  Optional Pushover user key; when provided, enables the Pushover channel.
     * @return $this The current notification instance for method chaining.
     */
    public function setChannels(array $channels, ?string $pushoverKey = null): self
    {
        $this->allowedChannels = $channels;
        $this->pushoverKey = $pushoverKey;

        return $this;
    }

    /**
     * Determine which channels the notification should be sent through based on configured allowed channels and the optional Pushover key.
     *
     * If 'mail' is allowed, the 'mail' channel is included. If 'pushover' is allowed and a Pushover key is present, the Pushover channel class is included.
     *
     * @param  object  $notifiable  The entity to which the notification will be sent.
     * @return array An array of channel identifiers (e.g., 'mail' or channel class names) to use for delivery.
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
     * Create the mail representation of the user promotion notification.
     *
     * @return \Illuminate\Notifications\Messages\MailMessage A mail message with the subject "Welcome to Traveler Status!", a personalized congratulatory greeting, instructions to set up a Minecraft account, an action button linking to the Minecraft settings page, and a closing line welcoming the user aboard.
     */
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

    /**
     * Create the Pushover notification payload for a user promoted to Traveler.
     *
     * @return array{title: string, message: string, url: string} Associative array with keys `title`, `message`, and `url`.
     */
    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Welcome to Traveler Status!',
            'message' => "You've been promoted to Traveler! Set up your Minecraft account to join the server.",
            'url' => url(route('settings.minecraft-accounts')),
        ];
    }
}
