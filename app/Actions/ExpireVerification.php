<?php

namespace App\Actions;

use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Services\MinecraftRconService;
use Lorisleiva\Actions\Concerns\AsAction;

class ExpireVerification
{
    use AsAction;

    /**
     * Expire a single pending verification.
     *
     * Marks the verification as expired, locates the associated MinecraftAccount
     * in 'verifying' state, marks it 'cancelled' (so it enters the retry pool if
     * RCON is unreachable), attempts synchronous whitelist removal, issues a
     * best-effort kick on success, then deletes the account.
     *
     * Returns true if the account was deleted, false if the account was not found
     * or the server was unreachable (account stays cancelled for the retry pass).
     */
    public function handle(MinecraftVerification $verification): bool
    {
        $verification->update(['status' => 'expired']);

        $account = MinecraftAccount::whereNormalizedUuid($verification->minecraft_uuid)
            ->verifying()
            ->where('user_id', $verification->user_id)
            ->first();

        if (! $account) {
            return false;
        }

        // Mark cancelled BEFORE attempting removal so it enters the retry pool
        // if the process crashes or the server is unreachable.
        $account->update(['status' => MinecraftAccountStatus::Cancelled]);

        $rcon = app(MinecraftRconService::class);

        $result = $rcon->executeCommand(
            $account->whitelistRemoveCommand(),
            'whitelist',
            $account->username,
            $verification->user,
            ['action' => 'cleanup_expired', 'verification_id' => $verification->id]
        );

        if ($result['success']) {
            // Best-effort kick; harmless if the player is already offline.
            $rcon->executeCommand(
                "kick \"{$account->username}\" Your verification has expired. Please re-verify to rejoin.",
                'kick',
                $account->username,
                $verification->user,
                ['action' => 'kick_expired_verification', 'verification_id' => $verification->id]
            );

            $account->delete();

            return true;
        }

        // Server offline — account stays cancelled for retryCancelledAccounts().
        return false;
    }
}
