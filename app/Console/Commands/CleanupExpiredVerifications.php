<?php

namespace App\Console\Commands;

use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Services\MinecraftRconService;
use Illuminate\Console\Command;

class CleanupExpiredVerifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'minecraft:cleanup-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove temporary whitelists for expired Minecraft verifications and retry cancelled accounts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->handleExpiredVerifications();
        $this->retryCancelledAccounts();

        return Command::SUCCESS;
    }

    /**
     * Pass 1: Handle newly expired pending verifications.
     * Marks the associated MinecraftAccount as 'cancelled', attempts sync whitelist removal,
     * and deletes the account if successful.
     */
    private function handleExpiredVerifications(): void
    {
        $expiredVerifications = MinecraftVerification::pending()
            ->expired()
            ->with('user')
            ->get();

        if ($expiredVerifications->isEmpty()) {
            $this->info('No expired verifications to clean up.');

            return;
        }

        $rconService = app(MinecraftRconService::class);
        $count = 0;

        foreach ($expiredVerifications as $verification) {
            // Mark verification expired first
            $verification->update(['status' => 'expired']);

            // Find the corresponding verifying account
            $account = MinecraftAccount::whereNormalizedUuid($verification->minecraft_uuid)
                ->verifying()
                ->where('user_id', $verification->user_id)
                ->first();

            if (! $account) {
                $this->warn("No verifying account found for verification #{$verification->id}");
                $count++;

                continue;
            }

            // Mark as cancelled BEFORE attempting removal so it enters the retry pool
            // if the process crashes or the server is unreachable
            $account->update(['status' => MinecraftAccountStatus::Cancelled]);

            $result = $rconService->executeCommand(
                $account->whitelistRemoveCommand(),
                'whitelist',
                $account->command_id,
                $verification->user,
                ['action' => 'cleanup_expired', 'verification_id' => $verification->id]
            );

            if ($result['success']) {
                // Best-effort kick; this can fail harmlessly if the user is offline.
                $rconService->executeCommand(
                    "kick \"{$account->username}\" Your verification has expired. Please re-verify to rejoin.",
                    'kick',
                    $account->command_id,
                    $verification->user,
                    ['action' => 'kick_expired_verification', 'verification_id' => $verification->id]
                );

                $account->delete();
                $this->info("Removed whitelist and deleted account for {$account->username}.");
            } else {
                $this->warn("Server offline — {$account->username} marked cancelled, will retry next cycle.");
            }

            $count++;
        }

        $this->info("Processed {$count} expired verification(s).");
    }

    /**
     * Pass 2: Retry whitelist removal for accounts stuck in 'cancelled' state
     * (i.e., server was offline during Pass 1 on a previous run).
     */
    private function retryCancelledAccounts(): void
    {
        $cancelledAccounts = MinecraftAccount::cancelled()
            ->with('user')
            ->get();

        if ($cancelledAccounts->isEmpty()) {
            return;
        }

        $rconService = app(MinecraftRconService::class);
        $removed = 0;

        foreach ($cancelledAccounts as $account) {
            $result = $rconService->executeCommand(
                $account->whitelistRemoveCommand(),
                'whitelist',
                $account->command_id,
                $account->user,
                ['action' => 'retry_cancelled_removal', 'account_id' => $account->id]
            );

            if ($result['success']) {
                $account->delete();
                $removed++;
                $this->info("Retry succeeded — removed whitelist and deleted {$account->username}.");
            } else {
                $this->warn("Retry failed for {$account->username} — will try again next cycle.");
            }
        }

        if ($removed > 0) {
            $this->info("Retry pass removed {$removed} cancelled account(s).");
        }
    }
}
