<?php

namespace App\Console\Commands;

use App\Models\MinecraftCommandLog;
use Illuminate\Console\Command;

class PruneDuplicateRconLogs extends Command
{
    protected $signature = 'minecraft:prune-duplicate-logs {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'One-time cleanup: remove duplicate whitelist-remove logs caused by the cancelled-account retry bug';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Find targets that have a "kick" entry (the legitimate expiry action)
        // followed by repeated "whitelist remove" entries (the bug duplicates).
        //
        // Strategy: for each target that has a kick command, find the last kick
        // timestamp, then delete all whitelist-remove commands for that target
        // that occurred AFTER that kick.

        $targets = MinecraftCommandLog::where('command_type', 'kick')
            ->distinct()
            ->pluck('target');

        $totalDeleted = 0;

        foreach ($targets as $target) {
            // Find the most recent kick for this target
            $lastKick = MinecraftCommandLog::where('command_type', 'kick')
                ->where('target', $target)
                ->orderByDesc('executed_at')
                ->first();

            if (! $lastKick) {
                continue;
            }

            // Count duplicate whitelist removes starting 1 minute after the last kick.
            // The legitimate whitelist remove may have happened just before the kick,
            // so we allow a 1-minute grace window to keep it.
            // Match both "whitelist remove" and "fwhitelist remove" (Floodgate variant).
            $cutoff = $lastKick->executed_at->copy()->addMinute();

            $query = MinecraftCommandLog::where('command_type', 'whitelist')
                ->where('target', $target)
                ->where(function ($q) {
                    $q->where('command', 'like', 'whitelist remove%')
                        ->orWhere('command', 'like', 'fwhitelist remove%');
                })
                ->where('executed_at', '>', $cutoff);

            $count = $query->count();

            if ($count === 0) {
                continue;
            }

            $this->info("{$target}: {$count} duplicate whitelist-remove entries after cutoff {$cutoff}");

            if (! $dryRun) {
                $deleted = $query->delete();
                $totalDeleted += $deleted;
                $this->info("  → Deleted {$deleted} entries.");
            } else {
                $totalDeleted += $count;
            }
        }

        if ($totalDeleted === 0) {
            $this->info('No duplicate logs found.');
        } elseif ($dryRun) {
            $this->warn("Dry run: {$totalDeleted} entries would be deleted. Run without --dry-run to proceed.");
        } else {
            $this->info("Done. Deleted {$totalDeleted} duplicate log entries total.");
        }

        return Command::SUCCESS;
    }
}
