<?php

namespace App\Actions;

use App\Enums\ReportLocation;
use App\Enums\ReportSeverity;
use App\Enums\ReportStatus;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\DisciplineReport;
use App\Models\ReportCategory;
use App\Models\User;
use App\Notifications\DisciplineReportPendingReviewNotification;
use App\Services\TicketNotificationService;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateDisciplineReport
{
    use AsAction;

    public function handle(
        User $subject,
        User $reporter,
        string $description,
        ReportLocation $location,
        string $actionsTaken,
        ReportSeverity $severity,
        ?string $witnesses = null,
        ?ReportCategory $category = null,
    ): DisciplineReport {
        $report = DisciplineReport::create([
            'subject_user_id' => $subject->id,
            'reporter_user_id' => $reporter->id,
            'report_category_id' => $category?->id,
            'description' => $description,
            'location' => $location,
            'witnesses' => $witnesses,
            'actions_taken' => $actionsTaken,
            'severity' => $severity,
            'status' => ReportStatus::Draft,
        ]);

        RecordActivity::run($subject, 'discipline_report_created',
            "Discipline report #{$report->id} created by {$reporter->name}. Severity: {$severity->label()}.", $reporter);

        // If reporter is not an Officer, notify Quartermaster dept for review
        if (! $reporter->isAtLeastRank(StaffRank::Officer)) {
            $this->notifyQuartermasterDepartment($report);
        }

        return $report;
    }

    private function notifyQuartermasterDepartment(DisciplineReport $report): void
    {
        $qmStaff = User::where('staff_department', StaffDepartment::Quartermaster)
            ->where('staff_rank', '!=', StaffRank::None)
            ->where('id', '!=', $report->reporter_user_id)
            ->get();

        $notificationService = app(TicketNotificationService::class);

        foreach ($qmStaff as $staffMember) {
            $notificationService->send($staffMember, new DisciplineReportPendingReviewNotification($report), 'staff_alerts');
        }
    }
}
