<?php

namespace App\Notifications;

use App\Models\StaffApplication;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewStaffApplicationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public StaffApplication $application,
    ) {
        $this->application->loadMissing(['user', 'staffPosition']);
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
        $applicant = $this->application->user?->name ?? 'Unknown Applicant';
        $position = $this->application->staffPosition?->title ?? 'Unknown Position';

        return (new MailMessage)
            ->subject("New Staff Application: {$applicant} for {$position}")
            ->line('A new staff application has been submitted.')
            ->line("**Applicant:** {$applicant}")
            ->line("**Position:** {$position}")
            ->action('Review Application', route('admin.applications.show', $this->application));
    }

    public function toPushover(object $notifiable): array
    {
        $applicant = $this->application->user?->name ?? 'Unknown Applicant';
        $position = $this->application->staffPosition?->title ?? 'Unknown Position';

        return [
            'title' => 'New Staff Application',
            'message' => "{$applicant} applied for {$position}",
            'url' => route('admin.applications.show', $this->application),
        ];
    }
}
