<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Models\StaffApplication;
use App\Models\User;
use App\Notifications\ApplicationStatusChangedNotification;
use App\Services\TicketNotificationService;
use Lorisleiva\Actions\Concerns\AsAction;

class DenyApplication
{
    use AsAction;

    public function handle(StaffApplication $application, User $reviewer, ?string $notes = null): void
    {
        $updates = [
            'status' => ApplicationStatus::Denied,
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

        RecordActivity::run($application, 'application_denied', "Denied by {$reviewer->name}.");

        app(TicketNotificationService::class)->send(
            $application->user,
            new ApplicationStatusChangedNotification($application, ApplicationStatus::Denied),
            'staff_alerts',
        );
    }
}
