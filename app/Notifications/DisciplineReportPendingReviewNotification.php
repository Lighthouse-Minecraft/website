<?php

namespace App\Notifications;

use App\Models\DisciplineReport;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisciplineReportPendingReviewNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(public DisciplineReport $report) {}

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
            ->subject('Discipline Report Pending Review')
            ->markdown('mail.discipline-report-pending-review', [
                'report' => $this->report,
                'profileUrl' => route('profile.show', $this->report->subject),
            ]);
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Discipline Report Needs Review',
            'message' => "New {$this->report->severity->label()} report about {$this->report->subject->name} by {$this->report->reporter->name}.",
            'url' => route('profile.show', $this->report->subject),
        ];
    }
}
