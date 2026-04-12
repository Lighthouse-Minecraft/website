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

class RulesAgreementReminderNotification extends Notification implements ShouldQueue
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
            ->subject('Reminder: Please Agree to the Updated Community Rules')
            ->greeting("Hello {$this->user->name},")
            ->line('You have not yet agreed to the latest version of the Lighthouse community rules.')
            ->line('Agreement is required to maintain your access to the community. Please log in and review the updated rules as soon as possible.')
            ->line('If you do not agree within the next two weeks, your account may be placed in a restricted status.')
            ->action('Review and Agree to Rules', route('rules.show'));
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Rules Agreement Required',
            'message' => 'You have not yet agreed to the updated community rules. Please log in to review them.',
            'url' => route('rules.show'),
        ];
    }
}
