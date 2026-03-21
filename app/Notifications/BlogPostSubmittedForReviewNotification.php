<?php

namespace App\Notifications;

use App\Models\BlogPost;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BlogPostSubmittedForReviewNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public BlogPost $post,
    ) {
        $this->post->loadMissing('author');
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

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $authorName = $this->post->author?->name ?? 'Unknown Author';

        return (new MailMessage)
            ->subject("Blog Post Submitted for Review: {$this->post->title}")
            ->line("A blog post has been submitted for review by {$authorName}.")
            ->line("**Title:** {$this->post->title}")
            ->action('Review Post', route('blog.manage'));
    }

    public function toPushover(object $notifiable): array
    {
        $authorName = $this->post->author?->name ?? 'Unknown Author';

        return [
            'title' => 'Blog Post Needs Review',
            'message' => "\"{$this->post->title}\" by {$authorName} is ready for review.",
            'url' => route('blog.manage'),
        ];
    }
}
