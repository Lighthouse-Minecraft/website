<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Enums\BackgroundCheckStatus;
use App\Enums\MessageKind;
use App\Enums\ThreadStatus;
use App\Models\Message;
use App\Models\StaffApplication;
use App\Models\Thread;
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
        $application->loadMissing(['staffPosition', 'user']);

        $updates = [
            'status' => ApplicationStatus::Approved,
            'background_check_status' => $bgCheck,
            'conditions' => $conditions,
            'reviewed_by' => $reviewer->id,
        ];

        $timestamp = now()->format('Y-m-d H:i');
        $newNote = "[{$timestamp}] [Approved] {$reviewer->name}".($notes ? ": {$notes}" : '');
        $updates['reviewer_notes'] = $application->reviewer_notes
            ? $application->reviewer_notes."\n".$newNote
            : $newNote;

        $application->update($updates);

        // Auto-assign the applicant to the staff position
        if ($application->staffPosition) {
            $application->staffPosition->update(['user_id' => $application->user_id]);
        }

        // Close related discussions with system messages
        $this->closeDiscussions($application, $notes);

        RecordActivity::run($application, 'application_approved', "Approved by {$reviewer->name}.");

        app(TicketNotificationService::class)->send(
            $application->user,
            new ApplicationStatusChangedNotification($application, ApplicationStatus::Approved),
            'staff_alerts',
        );
    }

    private function closeDiscussions(StaffApplication $application, ?string $notes): void
    {
        $systemUser = User::where('email', 'system@lighthouse.local')->first();

        $applicantName = $application->user->name ?? 'Applicant';
        $positionTitle = $application->staffPosition->title ?? 'position';

        $body = "**Application approved.** {$applicantName} has been assigned to {$positionTitle}.";

        if ($notes) {
            $body .= "\n\n**Notes:** {$notes}";
        }

        $body .= "\n\nThis discussion is now closed.";

        $threadIds = array_filter([
            $application->staff_review_thread_id,
            $application->interview_thread_id,
        ]);

        foreach ($threadIds as $threadId) {
            $thread = Thread::find($threadId);

            if (! $thread || $thread->status === ThreadStatus::Closed) {
                continue;
            }

            if ($systemUser) {
                Message::create([
                    'thread_id' => $thread->id,
                    'user_id' => $systemUser->id,
                    'body' => $body,
                    'kind' => MessageKind::System,
                ]);
            }

            $thread->update([
                'status' => ThreadStatus::Closed,
                'is_locked' => true,
            ]);
        }
    }
}
