<?php

namespace App\Actions;

use App\Enums\ReportLocation;
use App\Enums\ReportSeverity;
use App\Models\DisciplineReport;
use App\Models\ReportCategory;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateDisciplineReport
{
    use AsAction;

    public function handle(
        DisciplineReport $report,
        User $editor,
        string $description,
        ReportLocation $location,
        string $actionsTaken,
        ReportSeverity $severity,
        ?string $witnesses = null,
        ?ReportCategory $category = null,
    ): DisciplineReport {
        $report->update([
            'description' => $description,
            'location' => $location,
            'witnesses' => $witnesses,
            'actions_taken' => $actionsTaken,
            'severity' => $severity,
            'report_category_id' => $category?->id,
        ]);

        RecordActivity::run($report->subject, 'discipline_report_updated',
            "Discipline report #{$report->id} updated by {$editor->name}.", $editor);

        return $report->fresh();
    }
}
