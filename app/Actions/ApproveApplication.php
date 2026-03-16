<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Enums\BackgroundCheckStatus;
use App\Models\StaffApplication;
use App\Models\User;
use App\Notifications\ApplicationStatusChangedNotification;
use App\Services\TicketNotificationService;
use Lorisleiva\Actions\Concerns\AsAction;

class ApproveApplication
{
    use AsAction;

    public function handle(
        StaffApplication $application,
        User $reviewer,
        BackgroundCheckStatus $bgCheck,
        ?string $conditions = null,
        ?string $notes = null,
    ): void {
        $updates = [
            'status' => ApplicationStatus::Approved,
            'background_check_status' => $bgCheck,
            'conditions' => $conditions,
            'reviewed_by' => $reviewer->id,
        ];

        if ($notes) {
            $timestamp = now()->format('Y-m-d H:i');
            $newNote = "[{$timestamp}] {$reviewer->name}: {$notes}";
            $updates['reviewer_notes'] = $application->reviewer_notes
                ? $application->reviewer_notes."\n".$newNote
                : $newNote;
        }

        $application->update($updates);

        RecordActivity::run($application, 'application_approved', "Approved by {$reviewer->name}.");

        app(TicketNotificationService::class)->send(
            $application->user,
            new ApplicationStatusChangedNotification($application, ApplicationStatus::Approved),
            'staff_alerts',
        );
    }
}
