<?php

namespace App\Notifications;

use App\Models\Thread;
use App\Notifications\Channels\DiscordChannel;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewContactInquiryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public Thread $thread
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

        if (in_array('discord', $this->allowedChannels)) {
            $channels[] = DiscordChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Contact Inquiry: '.$this->thread->subject)
            ->markdown('mail.new-contact-inquiry', [
                'thread' => $this->thread,
            ]);
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'New Contact Inquiry',
            'message' => $this->thread->subject,
        ];
    }

    public function toDiscord(object $notifiable): string
    {
        $from = $this->thread->guest_name ?? $this->thread->guest_email ?? 'Unknown';

        return "**New Contact Inquiry:** {$this->thread->subject}\n**From:** {$from}";
    }
}
