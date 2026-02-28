<?php

namespace App\Actions;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class AutoAssignPrimaryAccount
{
    use AsAction;

    /**
     * Ensure the user has a primary Minecraft account if they have any active accounts.
     *
     * If the user already has a primary active account, this is a no-op.
     * Otherwise, the first active account (by ID) is set as primary.
     */
    public function handle(User $user): void
    {
        $hasPrimary = $user->minecraftAccounts()
            ->active()
            ->where('is_primary', true)
            ->exists();

        if ($hasPrimary) {
            return;
        }

        $nextAccount = $user->minecraftAccounts()
            ->active()
            ->orderBy('id')
            ->first();

        if ($nextAccount) {
            SetPrimaryMinecraftAccount::run($nextAccount);
        }
    }
}
