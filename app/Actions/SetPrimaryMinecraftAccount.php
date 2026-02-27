<?php

namespace App\Actions;

use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SetPrimaryMinecraftAccount
{
    use AsAction;

    /**
     * Set the given Minecraft account as the user's primary account.
     *
     * Clears the primary flag from all other accounts for the same user,
     * then sets the given account as primary. Only active accounts can be primary.
     */
    public function handle(MinecraftAccount $account): bool
    {
        if ($account->status !== MinecraftAccountStatus::Active) {
            return false;
        }

        DB::transaction(function () use ($account) {
            MinecraftAccount::where('user_id', $account->user_id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            $account->update(['is_primary' => true]);
        });

        return true;
    }
}
