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
     * in 'verifying' state, marks it 'cancelling' (so it enters the retry pool if
     * RCON is unreachable), attempts synchronous whitelist removal, and on success
     * transitions the account to 'cancelled' (final state where user can retry).
     *
     * Returns true if the whitelist removal succeeded, false if the account was
     * not found or the server was unreachable (account stays 'cancelling' for retry).
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

        // Mark as 'cancelling' BEFORE attempting removal so it enters the retry
        // pool if the process crashes or the server is unreachable.
        $account->update(['status' => MinecraftAccountStatus::Cancelling]);

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

            // Whitelist removed — transition to final 'cancelled' state so the
            // user can retry verification without re-entering their username.
            $account->update(['status' => MinecraftAccountStatus::Cancelled]);

            return true;
        }

        // Server offline — account stays 'cancelling' for retryCancelledAccounts().
        return false;
    }
}
