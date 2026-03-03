<?php

namespace App\Notifications;

use App\Models\DisciplineReport;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisciplineReportPublishedNotification extends Notification implements ShouldQueue
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
            ->subject('Discipline Report Filed')
            ->markdown('mail.discipline-report-published', [
                'report' => $this->report,
                'profileUrl' => route('profile.show', $this->report->subject),
            ]);
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Discipline Report Filed',
            'message' => "A {$this->report->severity->label()} discipline report has been filed. Location: {$this->report->location->label()}.",
            'url' => route('profile.show', $this->report->subject),
        ];
    }
}
