<?php

namespace App\Notifications;

use App\Models\Thread;
use App\Notifications\Channels\DiscordChannel;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewTopicNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public Thread $thread
    ) {
        $this->thread->loadMissing('createdBy');
    }

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
            ->subject('New Discussion Topic: '.$this->thread->subject)
            ->markdown('mail.new-topic', [
                'thread' => $this->thread,
                'topicUrl' => route('topics.show', $this->thread),
            ]);
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'New Discussion Topic',
            'message' => $this->thread->subject,
            'url' => route('topics.show', $this->thread),
        ];
    }

    public function toDiscord(object $notifiable): string
    {
        $creatorName = $this->thread->createdBy?->name ?? 'Unknown';

        return "**New Discussion Topic:** {$this->thread->subject}\n**Started by:** {$creatorName}\n".route('topics.show', $this->thread);
    }
}
