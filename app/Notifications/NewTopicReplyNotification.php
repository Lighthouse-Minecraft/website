<?php

namespace App\Notifications;

use App\Models\Message;
use App\Notifications\Channels\DiscordChannel;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewTopicReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public Message $message
    ) {
        $this->message->loadMissing(['thread', 'user']);
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
        $thread = $this->message->thread;

        return (new MailMessage)
            ->subject('New Reply: '.$thread->subject)
            ->markdown('mail.new-topic-reply', [
                'thread' => $thread,
                'fromName' => $this->message->user?->name ?? 'Unknown',
                'messagePreview' => Str::limit($this->message->body, 100),
                'topicUrl' => route('discussions.show', $thread),
            ]);
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'New Discussion Reply',
            'message' => Str::limit($this->message->body, 100),
            'url' => route('discussions.show', $this->message->thread),
        ];
    }

    public function toDiscord(object $notifiable): string
    {
        $thread = $this->message->thread;
        $fromName = $this->message->user?->name ?? 'Unknown';

        return "**New Discussion Reply:** {$thread->subject}\n**From:** {$fromName}\n".Str::limit($this->message->body, 200)."\n".route('discussions.show', $thread);
    }
}
