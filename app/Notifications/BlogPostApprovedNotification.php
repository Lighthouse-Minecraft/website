<?php

namespace App\Notifications;

use App\Models\BlogPost;
use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BlogPostApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public BlogPost $post,
        public User $reviewer,
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
        $status = $this->post->scheduled_at ? 'scheduled for publication' : 'published immediately';

        return (new MailMessage)
            ->subject("Your Blog Post Has Been Approved: {$this->post->title}")
            ->line("Your blog post \"{$this->post->title}\" has been approved by {$this->reviewer->name} and {$status}.")
            ->action('View Blog Management', route('blog.manage'));
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Blog Post Approved',
            'message' => "\"{$this->post->title}\" approved by {$this->reviewer->name}.",
            'url' => route('blog.manage'),
        ];
    }
}
