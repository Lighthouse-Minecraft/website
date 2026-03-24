<?php

namespace App\Actions;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncMinecraftPermissions
{
    use AsAction;

    /**
     * Sync all Minecraft permissions for a user across every active account.
     *
     * Delegates to SyncMinecraftAccount for each active account, which applies
     * the authoritative desired state: whitelist eligibility, member rank, and
     * staff assignment — all in one consistent synchronous pass.
     */
    public function handle(User $user): void
    {
        $activeAccounts = $user->minecraftAccounts()->active()->get();

        foreach ($activeAccounts as $account) {
            SyncMinecraftAccount::run($account);
        }
    }
}
