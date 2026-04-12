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
        // Find credentials assigned to this position that the departing user accessed
        $credentials = $position->credentials()
            ->whereHas('accessLogs', fn ($q) => $q->where('user_id', $user->id))
            ->get();

        foreach ($credentials as $credential) {
            $credential->update(['needs_password_change' => true]);
        }
    }
}
