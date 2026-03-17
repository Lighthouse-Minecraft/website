<?php

namespace App\Actions;

use App\Enums\BackgroundCheckStatus;
use App\Enums\MessageKind;
use App\Models\Message;
use App\Models\StaffApplication;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateBackgroundCheck
{
    use AsAction;

    public function handle(
        StaffApplication $application,
        User $reviewer,
        BackgroundCheckStatus $bgCheck,
        ?string $notes = null,
    ): void {
        $updates = [
            'background_check_status' => $bgCheck,
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

        // Post system message in staff review discussion
        if ($application->staff_review_thread_id) {
            $systemUser = User::where('email', 'system@lighthouse.local')->first();

            if ($systemUser) {
                Message::create([
                    'thread_id' => $application->staff_review_thread_id,
                    'user_id' => $systemUser->id,
                    'body' => "**Background check updated to {$bgCheck->label()}** by {$reviewer->name}.",
                    'kind' => MessageKind::System,
                ]);
            }
        }

        RecordActivity::run(
            $application,
            'background_check_updated',
            "Background check updated to {$bgCheck->label()} by {$reviewer->name}.",
        );
    }
}
