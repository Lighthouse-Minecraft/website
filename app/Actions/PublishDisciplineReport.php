<?php

namespace App\Actions;

use App\Enums\ReportStatus;
use App\Models\DisciplineReport;
use App\Models\User;
use App\Notifications\DisciplineReportPublishedNotification;
use App\Notifications\DisciplineReportPublishedParentNotification;
use App\Services\TicketNotificationService;
use Lorisleiva\Actions\Concerns\AsAction;

class PublishDisciplineReport
{
    use AsAction;

    public function handle(DisciplineReport $report, User $publisher): DisciplineReport
    {
        $report->update([
            'status' => ReportStatus::Published,
            'publisher_user_id' => $publisher->id,
            'published_at' => now(),
        ]);

        $report->subject->clearDisciplineRiskScoreCache();

        RecordActivity::run($report->subject, 'discipline_report_published',
            "Discipline report #{$report->id} published by {$publisher->name}. Severity: {$report->severity->label()}.");

        $this->notifySubjectAndParents($report);

        return $report->fresh();
    }

    private function notifySubjectAndParents(DisciplineReport $report): void
    {
        $notificationService = app(TicketNotificationService::class);

        $notificationService->send(
            $report->subject,
            new DisciplineReportPublishedNotification($report),
            'account'
        );

        $parentNotification = new DisciplineReportPublishedParentNotification($report);
        foreach ($report->subject->parents as $parent) {
            $notificationService->send($parent, $parentNotification, 'account');
        }
    }
}
