<?php

namespace App\Actions;

use App\Enums\MessageFlagStatus;
use App\Models\MessageFlag;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class AcknowledgeFlag
{
    use AsAction;

    public function handle(MessageFlag $flag, User $reviewer, ?string $staffNotes = null): void
    {
        // Update the flag
        $flag->update([
            'status' => MessageFlagStatus::Acknowledged,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'staff_notes' => $staffNotes,
        ]);

        // Recalculate has_open_flags on the original thread
        $thread = $flag->thread;
        $hasOpenFlags = $thread->flags()
            ->where('status', MessageFlagStatus::New)
            ->exists();

        $thread->update([
            'has_open_flags' => $hasOpenFlags,
        ]);

        // Record activity
        $notesPreview = $staffNotes ? ' - '.substr($staffNotes, 0, 50) : '';
        RecordActivity::run(
            $thread,
            'flag_acknowledged',
            "Flag acknowledged by {$reviewer->name}{$notesPreview}"
        );
    }
}
