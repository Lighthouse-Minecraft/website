<?php

namespace App\Notifications;

use App\Models\RuleVersion;
use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class RulesVersionPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public User $user,
        public RuleVersion $version
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
            ->subject('Community Rules Updated — Please Review and Agree')
            ->greeting("Hello {$this->user->name},")
            ->line('The Lighthouse community rules have been updated and require your agreement.')
            ->line('Please log in and review the updated rules. Your continued access to the community depends on your agreement.')
            ->action('Review and Agree to Rules', route('dashboard'));
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Community Rules Updated',
            'message' => 'The community rules have been updated and require your agreement. Please log in to review them.',
            'url' => route('dashboard'),
        ];
    }
}
