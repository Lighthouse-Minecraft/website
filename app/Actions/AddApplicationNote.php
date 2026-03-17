<?php

namespace App\Actions;

use App\Models\StaffApplication;
use App\Models\StaffApplicationNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AddApplicationNote
{
    use AsAction;

    public function handle(StaffApplication $application, User $staff, string $body): StaffApplicationNote
    {
        return DB::transaction(function () use ($application, $staff, $body) {
            $note = $application->notes()->create([
                'user_id' => $staff->id,
                'body' => $body,
            ]);

            RecordActivity::run($application, 'application_note_added', "{$staff->name} added a note.");

            return $note;
        });
    }
}
