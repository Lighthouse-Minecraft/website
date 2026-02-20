<?php

namespace App\Console\Commands;

use App\Enums\MinecraftAccountType;
use App\Models\MinecraftAccount;
use App\Services\McProfileService;
use App\Services\MojangApiService;
use Illuminate\Console\Command;

class RefreshMinecraftUsernames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'minecraft:refresh-usernames';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Minecraft usernames via staggered API checks (30-day cycle)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $totalAccounts = MinecraftAccount::active()->count();

        if ($totalAccounts === 0) {
            $this->info('No Minecraft accounts to refresh.');

            return Command::SUCCESS;
        }

        // Calculate daily batch size to check all accounts over 30 days
        $dailyBatchSize = max(1, ceil($totalAccounts / 30));

        // Select only active accounts, prioritizing active users (by last_login_at) then oldest checks
        // Skip accounts checked within the last 30 days
        $accounts = MinecraftAccount::active()
            ->join('users', 'minecraft_accounts.user_id', '=', 'users.id')
            ->select('minecraft_accounts.*')
            ->where(function ($query) {
                $query->whereNull('minecraft_accounts.last_username_check_at')
                    ->orWhere('minecraft_accounts.last_username_check_at', '<', now()->subDays(30));
            })
            ->orderByRaw('users.last_login_at IS NULL, users.last_login_at DESC')
            ->orderBy('minecraft_accounts.last_username_check_at', 'asc')
            ->limit($dailyBatchSize)
            ->get();

        $mojangService = app(MojangApiService::class);
        $mcProfileService = app(McProfileService::class);
        $updated = 0;
        $failed = 0;

        foreach ($accounts as $account) {
            $newUsername = null;

            if ($account->account_type === MinecraftAccountType::Java) {
                $newUsername = $mojangService->getJavaUsername($account->uuid);
            } else {
                $newUsername = $mcProfileService->getBedrockGamertag($account->uuid);
            }

            if ($newUsername && $newUsername !== $account->username) {
                $oldUsername = $account->username;
                $account->update([
                    'username' => $newUsername,
                    'last_username_check_at' => now(),
                ]);
                $this->info("Updated {$oldUsername} -> {$newUsername}");
                $updated++;
            } elseif ($newUsername) {
                // No change, just update check timestamp
                $account->update(['last_username_check_at' => now()]);
            } else {
                // API failed, update check timestamp to avoid blocking future batches
                $account->update(['last_username_check_at' => now()]);
                $this->warn("Failed to refresh username for {$account->username}");
                $failed++;
            }
        }

        $this->info("Refreshed {$accounts->count()} account(s): {$updated} updated, {$failed} failed.");

        return Command::SUCCESS;
    }
}
