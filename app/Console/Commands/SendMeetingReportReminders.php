<?php

namespace App\Console\Commands;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\User;
use App\Notifications\MeetingReportReminderNotification;
use App\Services\TicketNotificationService;
use Illuminate\Console\Command;

class SendMeetingReportReminders extends Command
{
    protected $signature = 'meetings:send-report-reminders';

    protected $description = 'Send reminders to staff who have not submitted their staff check-ins';

    public function handle(): int
    {
        $notifyDays = config('lighthouse.meeting_report_notify_days', 3);

        $meetings = Meeting::where('type', MeetingType::StaffMeeting)
            ->where('status', MeetingStatus::Pending)
            ->where('scheduled_time', '<=', now()->addDays($notifyDays))
            ->where('scheduled_time', '>', now())
            ->has('questions')
            ->get();

        if ($meetings->isEmpty()) {
            $this->info('No meetings requiring check-in reminders.');

            return Command::SUCCESS;
        }

        $notificationService = app(TicketNotificationService::class);
        $totalSent = 0;

        $staffUsers = User::whereNotNull('staff_rank')
            ->where('staff_rank', '>=', StaffRank::JrCrew->value)
            ->get();

        $existingReports = MeetingReport::whereIn('meeting_id', $meetings->pluck('id'))
            ->whereIn('user_id', $staffUsers->pluck('id'))
            ->get()
            ->keyBy(fn ($r) => $r->meeting_id.'|'.$r->user_id);

        foreach ($meetings as $meeting) {
            foreach ($staffUsers as $user) {
                $existingReport = $existingReports->get($meeting->id.'|'.$user->id);

                if ($existingReport && ($existingReport->submitted_at || $existingReport->notified_at)) {
                    continue;
                }

                $notificationService->send(
                    $user,
                    new MeetingReportReminderNotification($meeting),
                    'staff_alerts'
                );

                if (! $existingReport) {
                    MeetingReport::create([
                        'meeting_id' => $meeting->id,
                        'user_id' => $user->id,
                        'notified_at' => now(),
                    ]);
                } else {
                    $existingReport->update(['notified_at' => now()]);
                }

                $totalSent++;
                $this->line("  Notified: {$user->name} for {$meeting->title}");
            }
        }

        $this->info("Sent {$totalSent} reminder(s).");

        return Command::SUCCESS;
    }
}
