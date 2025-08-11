<?php

namespace App\Actions;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;

class AcknowledgeAnnouncement
{
    use AsAction;

    public function handle(Announcement $announcement, ?User $user)
    {
        if (! $user) {
            if (! Auth::check()) {
                throw new \Exception(message: 'User must be authenticated to acknowledge an announcement.');
            }
            // If the user is not passed, use the authenticated user
            $user = Auth::user();
        }

        $user->acknowledgedAnnouncements()->syncWithoutDetaching([$announcement->id]);
    }
}
