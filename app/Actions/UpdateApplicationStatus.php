<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Enums\BackgroundCheckStatus;
use App\Enums\MessageKind;
use App\Enums\StaffRank;
use App\Models\Message;
use App\Models\StaffApplication;
use App\Models\User;
use App\Notifications\ApplicationStatusChangedNotification;
use App\Services\TicketNotificationService;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateApplicationStatus
{
    use AsAction;

    public function handle(
        StaffApplication $application,
        ApplicationStatus $newStatus,
        User $reviewer,
        ?string $notes = null,
        ?BackgroundCheckStatus $bgCheck = null,
    ): void {
        DB::transaction(function () use ($application, $newStatus, $reviewer, $notes, $bgCheck) {
            $updates = [
                'status' => $newStatus,
                'reviewed_by' => $reviewer->id,
            ];

            $timestamp = now()->format('Y-m-d H:i');
            $newNote = "[{$timestamp}] [{$newStatus->label()}] {$reviewer->name}".($notes ? ": {$notes}" : '');
            $updates['reviewer_notes'] = $application->reviewer_notes
                ? $application->reviewer_notes."\n".$newNote
                : $newNote;

            if ($newStatus === ApplicationStatus::BackgroundCheck) {
                $updates['background_check_status'] = $bgCheck ?? BackgroundCheckStatus::Pending;
            }

            $application->update($updates);

            // Create interview discussion when moving to Interview (skip if already exists)
            if ($newStatus === ApplicationStatus::Interview && ! $application->interview_thread_id) {
                $this->createInterviewDiscussion($application, $reviewer);
            }

            // Post system message in staff review discussion
            $this->postStatusChangeMessage($application, $newStatus, $reviewer);

            RecordActivity::run(
                $application,
                'application_status_changed',
                "Status changed to {$newStatus->label()} by {$reviewer->name}.",
            );
        });

        $notificationService = app(TicketNotificationService::class);
        $notificationService->send(
            $application->user,
            new ApplicationStatusChangedNotification($application, $newStatus),
            'staff_alerts',
        );
    }

    private function createInterviewDiscussion(StaffApplication $application, User $reviewer): void
    {
        $application->loadMissing(['user', 'staffPosition']);
        $applicant = $application->user;
        $position = $application->staffPosition;

        $thread = CreateTopic::run(
            $application,
            $reviewer,
            "Interview: {$applicant->name} for {$position->title}",
            "This discussion is for scheduling and coordinating the interview for {$applicant->name}'s application for {$position->title}.",
        );

        // Add the applicant
        $thread->addParticipant($applicant);

        // Add all Officers (any department), department crew, and admins
        $staffUsers = User::where(function ($q) use ($position) {
            // All Officers
            $q->where('staff_rank', '>=', StaffRank::Officer->value)
            // Department crew
                ->orWhere(function ($sub) use ($position) {
                    $sub->where('staff_department', $position->department)
                        ->where('staff_rank', '>=', StaffRank::JrCrew->value);
                });
        })
            ->where('staff_rank', '!=', StaffRank::None->value)
            ->where('id', '!=', $reviewer->id)
            ->where('id', '!=', $applicant->id)
            ->get();

        $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'Admin'))
            ->where('id', '!=', $reviewer->id)
            ->where('id', '!=', $applicant->id)
            ->get();

        foreach ($staffUsers->merge($admins)->unique('id') as $staffUser) {
            $thread->addParticipant($staffUser);
        }

        $application->update(['interview_thread_id' => $thread->id]);
    }

    private function postStatusChangeMessage(StaffApplication $application, ApplicationStatus $newStatus, User $reviewer): void
    {
        if (! $application->staff_review_thread_id) {
            return;
        }

        $systemUser = User::where('email', 'system@lighthouse.local')->first();

        if (! $systemUser) {
            return;
        }

        Message::create([
            'thread_id' => $application->staff_review_thread_id,
            'user_id' => $systemUser->id,
            'body' => "**Application status changed to {$newStatus->label()}** by {$reviewer->name}.",
            'kind' => MessageKind::System,
        ]);
    }
}
