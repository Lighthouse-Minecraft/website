<?php

namespace App\Notifications;

use App\Models\DisciplineReport;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisciplineReportPublishedParentNotification extends Notification implements ShouldQueue
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
            ->subject('Staff Report Recorded for Your Child')
            ->markdown('mail.discipline-report-published-parent', [
                'report' => $this->report,
                'childName' => $this->report->subject->name,
                'portalUrl' => route('parent-portal.index'),
            ]);
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Staff Report Recorded',
            'message' => "A {$this->report->severity->label()} staff report has been recorded regarding your child {$this->report->subject->name}.",
            'url' => route('parent-portal.index'),
        ];
    }
}
