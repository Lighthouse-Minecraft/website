<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChildWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public User $child,
        public User $parent
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
            ->subject('Welcome to Lighthouse!')
            ->markdown('mail.child-welcome', [
                'parentName' => $this->parent->name,
                'childName' => $this->child->name,
                'resetUrl' => route('password.request'),
            ]);
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Welcome to Lighthouse!',
            'message' => "Your parent {$this->parent->name} has created an account for you on Lighthouse! Use the password reset feature to gain access.",
            'url' => route('password.request'),
        ];
    }
}
