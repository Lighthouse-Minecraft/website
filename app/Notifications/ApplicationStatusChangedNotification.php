<?php

namespace App\Notifications;

use App\Enums\ApplicationStatus;
use App\Models\StaffApplication;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public StaffApplication $application,
        public ApplicationStatus $status,
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
        $position = $this->application->staffPosition->title;

        $subject = match ($this->status) {
            ApplicationStatus::Submitted => "Application Received — {$position}",
            ApplicationStatus::UnderReview => "Application Under Review — {$position}",
            ApplicationStatus::Interview => "Interview Scheduled — {$position}",
            ApplicationStatus::BackgroundCheck => "Background Check in Progress — {$position}",
            ApplicationStatus::Approved => "Application Approved — {$position}",
            ApplicationStatus::Denied => "Application Update — {$position}",
            ApplicationStatus::Withdrawn => "Application Withdrawn — {$position}",
        };

        $message = match ($this->status) {
            ApplicationStatus::Submitted => "Your application for {$position} has been received. We will review it and get back to you.",
            ApplicationStatus::UnderReview => "Your application for {$position} is now under review by our team.",
            ApplicationStatus::Interview => "You've been selected for an interview for {$position}. Check your discussions for scheduling details.",
            ApplicationStatus::BackgroundCheck => "Your application for {$position} has moved to the background check stage.",
            ApplicationStatus::Approved => "Congratulations! Your application for {$position} has been approved.",
            ApplicationStatus::Denied => "We appreciate your interest in {$position}. Unfortunately, your application was not approved at this time.",
            ApplicationStatus::Withdrawn => "Your application for {$position} has been withdrawn.",
        };

        return (new MailMessage)
            ->subject($subject)
            ->line($message)
            ->action('View Application', route('applications.show', $this->application));
    }

    public function toPushover(object $notifiable): array
    {
        $position = $this->application->staffPosition->title;

        return [
            'title' => "Application: {$this->status->label()}",
            'message' => "Your application for {$position} status: {$this->status->label()}",
            'url' => route('applications.show', $this->application),
        ];
    }
}
