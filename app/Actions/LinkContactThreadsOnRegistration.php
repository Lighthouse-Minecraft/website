<?php

namespace App\Actions;

use App\Enums\ThreadType;
use App\Models\Thread;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class LinkContactThreadsOnRegistration
{
    use AsAction;

    public function handle(User $newUser): void
    {
        $threads = Thread::where('type', ThreadType::ContactInquiry)
            ->whereRaw('LOWER(guest_email) = ?', [strtolower($newUser->email)])
            ->get();

        foreach ($threads as $thread) {
            $thread->addParticipant($newUser);
        }
    }
}
