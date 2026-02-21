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

    public function __construct(
        public User $user,
        public string $reason,
        public ?\Illuminate\Support\Carbon $expiresAt = null
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
        $mail = (new MailMessage)
            ->subject('You Have Been Placed in the Brig')
            ->line('Your Lighthouse account has been placed in the Brig by a staff member.')
            ->line('**Reason:** '.$this->reason);

        if ($this->expiresAt) {
            $mail->line('**Appeal available after:** '.$this->expiresAt->format('F j, Y \a\t g:i A T'));
            $mail->line('You will receive a notification when your appeal window opens.');
        } else {
            $mail->line('You may submit an appeal at any time via your dashboard.');
            $mail->action('Go to Dashboard', url('/dashboard'));
        }

        $mail->line('Your Minecraft server access has been suspended during this period.');

        return $mail;
    }

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
