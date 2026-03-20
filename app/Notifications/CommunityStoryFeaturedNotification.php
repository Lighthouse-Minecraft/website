<?php

namespace App\Notifications;

use App\Models\BlogPost;
use App\Models\CommunityResponse;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommunityStoryFeaturedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public BlogPost $post,
        public CommunityResponse $response,
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
        $blogUrl = route('blog.show', $this->post->slug);

        return (new MailMessage)
            ->subject("Your Story Was Featured: {$this->post->title}")
            ->line("Your community story was featured in the blog post \"{$this->post->title}\"!")
            ->line('Thank you for sharing your story with the community.')
            ->action('Read the Post', $blogUrl);
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Your Story Was Featured!',
            'message' => "Your community story was featured in \"{$this->post->title}\".",
            'url' => route('blog.show', $this->post->slug),
        ];
    }
}
