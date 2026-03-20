<?php

namespace App\Notifications;

use App\Models\BlogPost;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BlogPostPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public BlogPost $post,
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
            ->subject("New Blog Post: {$this->post->title}")
            ->line("A new blog post has been published: \"{$this->post->title}\" by {$this->post->author->name}.")
            ->action('Read Post', route('blog.show', $this->post->slug));
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'New Blog Post Published',
            'message' => "\"{$this->post->title}\" by {$this->post->author->name}.",
            'url' => route('blog.show', $this->post->slug),
        ];
    }
}
