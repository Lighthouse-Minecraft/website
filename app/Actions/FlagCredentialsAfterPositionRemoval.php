<?php

namespace App\Actions;

use App\Models\StaffPosition;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class FlagCredentialsAfterPositionRemoval
{
    use AsAction;

    public function handle(User $user, StaffPosition $position): void
    {
        // Flag credentials on this position that the departing user actually accessed
        $position->credentials()
            ->whereHas('accessLogs', fn ($accessLogQuery) => $accessLogQuery->where('user_id', $user->id))
            ->update(['needs_password_change' => true]);
    }
}
