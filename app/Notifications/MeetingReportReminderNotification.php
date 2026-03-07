<?php

namespace App\Notifications;

use App\Models\Meeting;
use App\Notifications\Channels\DiscordChannel;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReportReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public Meeting $meeting
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

        if (in_array('discord', $this->allowedChannels)) {
            $channels[] = DiscordChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reminder: Submit your check-in for '.$this->meeting->title)
            ->line('A staff meeting is coming up and you haven\'t submitted your staff check-in yet.')
            ->line('**Meeting:** '.$this->meeting->title)
            ->line('**Scheduled:** '.$this->meeting->scheduled_time->setTimezone('America/New_York')->format('F j, Y g:i A').' ET')
            ->action('Submit Check-In', route('meeting.report', $this->meeting))
            ->line('Please submit your check-in before the meeting starts.');
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Staff Check-In Reminder',
            'message' => 'Please submit your staff check-in for '.$this->meeting->title.' before the meeting starts.',
            'url' => route('meeting.report', $this->meeting),
        ];
    }

    public function toDiscord(object $notifiable): string
    {
        return "**Staff Check-In Reminder**\nPlease submit your staff check-in for **{$this->meeting->title}** scheduled on {$this->meeting->scheduled_time->setTimezone('America/New_York')->format('M j, Y g:i A')} ET.\n".route('meeting.report', $this->meeting);
    }
}
