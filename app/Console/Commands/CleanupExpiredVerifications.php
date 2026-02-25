<?php

namespace App\Console\Commands;

use App\Actions\ExpireVerification;
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
     * Delegates per-verification cleanup to ExpireVerification.
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

        $count = 0;

        foreach ($expiredVerifications as $verification) {
            $removed = ExpireVerification::run($verification);

            if ($removed) {
                $this->info("Removed whitelist and deleted account for {$verification->minecraft_username}.");
            } else {
                $this->warn("Could not remove {$verification->minecraft_username} — account not found or server offline.");
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
                $account->username,
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
