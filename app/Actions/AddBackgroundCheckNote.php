<?php

namespace App\Actions;

use App\Models\BackgroundCheck;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class AddBackgroundCheckNote
{
    use AsAction;

    public function handle(BackgroundCheck $check, string $noteText, User $author): void
    {
        $timestamp = now()->format('Y-m-d H:i');
        $newEntry = "[{$timestamp}] {$author->name}: {$noteText}";

        $check->notes = $check->notes
            ? $check->notes."\n".$newEntry
            : $newEntry;

        $check->save();

        RecordActivity::run(
            $check,
            'background_check_note_added',
            "Note added to background check by {$author->name}.",
            $author,
        );
    }
}
