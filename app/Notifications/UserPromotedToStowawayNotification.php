<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\DiscordChannel;
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

    /**
     * Create a notification for a user who has been promoted to stowaway.
     *
     * @param  \App\Models\User  $newStowaway  The user who was promoted to stowaway.
     */
    public function __construct(
        public User $newStowaway
    ) {}

    /**
     * Configure the notification's allowed delivery channels and optional Pushover API key.
     *
     * @param  array  $channels  Allowed notification channels (e.g., 'mail', 'pushover').
     * @param  string|null  $pushoverKey  Optional Pushover API key to enable the Pushover channel.
     * @return self The notification instance for method chaining.
     */
    public function setChannels(array $channels, ?string $pushoverKey = null): self
    {
        $this->allowedChannels = $channels;
        $this->pushoverKey = $pushoverKey;

        return $this;
    }

    /**
     * Determine the active delivery channels for this notification based on the instance configuration.
     *
     * Includes the mail channel when enabled and includes the Pushover channel only when enabled and a Pushover key is configured.
     *
     * @return array An array of channel identifiers or classes to be used for delivering the notification.
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

        if (in_array('discord', $this->allowedChannels)) {
            $channels[] = DiscordChannel::class;
        }

        return $channels;
    }

    /**
     * Build the email notifying recipients that a user has been promoted to stowaway.
     *
     * The message includes the promoted user's name, a request to review or manage the account,
     * an action button linking to the user's profile, and a closing thank-you line.
     *
     * @param  object  $notifiable  The entity that will receive the notification.
     * @return \Illuminate\Notifications\Messages\MailMessage The prepared mail message.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Stowaway User: '.$this->newStowaway->name)
            ->line($this->newStowaway->name.' has agreed to the rules and is awaiting Stowaway review.')
            ->line('Please review their profile and promote or manage their account as appropriate.')
            ->action('View Profile', url(route('profile.show', $this->newStowaway)))
            ->line('Thank you for your service!');
    }

    /**
     * Build the Pushover notification payload for the promoted user.
     *
     * The returned array contains the notification title, a short message about the
     * new stowaway, and a URL linking to the promoted user's profile.
     *
     * @return array{title:string,message:string,url:string} Associative array with keys:
     *                                                       - `title`: notification title
     *                                                       - `message`: brief message describing the promotion
     *                                                       - `url`: absolute URL to the promoted user's profile
     */
    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'New Stowaway: '.$this->newStowaway->name,
            'message' => $this->newStowaway->name.' has agreed to the rules and is awaiting review.',
            'url' => url(route('profile.show', $this->newStowaway)),
        ];
    }

    public function toDiscord(object $notifiable): string
    {
        return "**New Stowaway User:** {$this->newStowaway->name}\nThey have agreed to the rules and are awaiting review.\n".url(route('profile.show', $this->newStowaway));
    }
}
