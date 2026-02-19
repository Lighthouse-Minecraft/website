<?php

namespace App\Console\Commands;

use App\Actions\SendMinecraftCommand;
use App\Models\MinecraftVerification;
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
    protected $description = 'Remove temporary whitelists for expired Minecraft verifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expiredVerifications = MinecraftVerification::pending()
            ->expired()
            ->with('user')
            ->get();

        if ($expiredVerifications->isEmpty()) {
            $this->info('No expired verifications to clean up.');

            return Command::SUCCESS;
        }

        $count = 0;

        foreach ($expiredVerifications as $verification) {
            // Send whitelist remove command asynchronously
            SendMinecraftCommand::dispatch(
                "whitelist remove {$verification->minecraft_username}",
                'whitelist',
                $verification->minecraft_username,
                $verification->user,
                ['action' => 'cleanup_expired', 'verification_id' => $verification->id]
            );

            // Mark as expired
            $verification->update(['status' => 'expired']);

            $count++;
        }

        $this->info("Cleaned up {$count} expired verification(s).");

        return Command::SUCCESS;
    }
}
