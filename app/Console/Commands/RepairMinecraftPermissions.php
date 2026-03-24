<?php

namespace App\Console\Commands;

use App\Models\MinecraftAccount;
use App\Services\MinecraftRconService;
use Illuminate\Console\Command;

class RepairMinecraftPermissions extends Command
{
    protected $signature = 'minecraft:repair-permissions
        {--dry-run : Print planned actions without sending Minecraft commands}
        {--pace=1 : Seconds to pause between outbound commands (use 0 for testing)}';

    protected $description = 'Repair Minecraft whitelist, member rank, and staff state for all active accounts';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $pace = max(0, (int) $this->option('pace'));

        $mode = $dryRun ? 'DRY RUN' : 'LIVE';
        $this->info("minecraft:repair-permissions [{$mode}]");
        $this->newLine();

        $accounts = MinecraftAccount::active()->with('user')->get();

        if ($accounts->isEmpty()) {
            $this->info('No active Minecraft accounts found.');

            return Command::SUCCESS;
        }

        $counts = [
            'adds' => 0,
            'removes' => 0,
            'rank_changes' => 0,
            'staff_changes' => 0,
            'failures' => 0,
        ];

        $rcon = $dryRun ? null : app(MinecraftRconService::class);
        $firstCommand = true;

        foreach ($accounts as $account) {
            $user = $account->user;
            $rank = $user->membership_level->minecraftRank();
            $eligible = $rank !== null && ! $user->isInBrig() && $user->parent_allows_minecraft;

            $this->line("  <fg=cyan>{$account->username}</> ({$user->name})");

            if ($eligible) {
                $staffDepartment = $user->staff_department;

                // ── Whitelist add ─────────────────────────────────────────────
                $whitelistCmd = $account->whitelistAddCommand();
                if ($dryRun) {
                    $this->line("    [dry-run] {$whitelistCmd}");
                    $counts['adds']++;
                } else {
                    $firstCommand = $this->pauseIfNeeded($firstCommand, $pace);
                    $result = $rcon->executeCommand(
                        $whitelistCmd, 'whitelist', $account->username, $user,
                        ['action' => 'repair_whitelist_add']
                    );
                    $this->reportResult($whitelistCmd, $result['success']);
                    $result['success'] ? $counts['adds']++ : $counts['failures']++;
                }

                // ── Member rank ───────────────────────────────────────────────
                $rankCmd = "lh setmember {$account->username} {$rank}";
                if ($dryRun) {
                    $this->line("    [dry-run] {$rankCmd}");
                    $counts['rank_changes']++;
                } else {
                    $firstCommand = $this->pauseIfNeeded($firstCommand, $pace);
                    $result = $rcon->executeCommand(
                        $rankCmd, 'rank', $account->username, $user,
                        ['action' => 'repair_rank', 'rank' => $rank]
                    );
                    $this->reportResult($rankCmd, $result['success']);
                    $result['success'] ? $counts['rank_changes']++ : $counts['failures']++;
                }

                // ── Staff position ────────────────────────────────────────────
                $staffCmd = $staffDepartment !== null
                    ? "lh setstaff {$account->username} {$staffDepartment->value}"
                    : "lh removestaff {$account->username}";

                if ($dryRun) {
                    $this->line("    [dry-run] {$staffCmd}");
                    $counts['staff_changes']++;
                } else {
                    $firstCommand = $this->pauseIfNeeded($firstCommand, $pace);
                    $result = $rcon->executeCommand(
                        $staffCmd, 'staff', $account->username, $user,
                        ['action' => $staffDepartment !== null ? 'repair_staff_set' : 'repair_staff_remove']
                    );
                    $this->reportResult($staffCmd, $result['success']);
                    $result['success'] ? $counts['staff_changes']++ : $counts['failures']++;
                }
            } else {
                // ── Ineligible: whitelist remove ──────────────────────────────
                $reason = $this->ineligibilityReason($user, $rank);
                $removeCmd = $account->whitelistRemoveCommand();

                if ($dryRun) {
                    $this->line("    [dry-run] {$removeCmd}");
                    $this->line("      ({$reason})");
                    $counts['removes']++;
                } else {
                    $firstCommand = $this->pauseIfNeeded($firstCommand, $pace);
                    $result = $rcon->executeCommand(
                        $removeCmd, 'whitelist', $account->username, $user,
                        ['action' => 'repair_whitelist_remove', 'reason' => $reason]
                    );
                    $this->reportResult($removeCmd, $result['success']);
                    $result['success'] ? $counts['removes']++ : $counts['failures']++;
                }
            }
        }

        $this->printSummary($counts, $dryRun);

        return Command::SUCCESS;
    }

    /**
     * Sleep between commands if this is not the first outbound command in the run.
     * Returns false to signal the caller to clear the "first command" flag.
     */
    private function pauseIfNeeded(bool $firstCommand, int $pace): bool
    {
        if (! $firstCommand && $pace > 0) {
            sleep($pace);
        }

        return false;
    }

    private function reportResult(string $command, bool $success): void
    {
        if ($success) {
            $this->line("    <fg=green>✓</> {$command}");
        } else {
            $this->line("    <fg=red>✗</> {$command}");
        }
    }

    private function ineligibilityReason(mixed $user, ?string $rank): string
    {
        if ($rank === null) {
            return 'below server access threshold';
        }
        if ($user->isInBrig()) {
            return 'in brig';
        }

        return 'parent disabled';
    }

    private function printSummary(array $counts, bool $dryRun): void
    {
        $prefix = $dryRun ? '[dry-run] ' : '';

        $this->newLine();
        $this->info('─── Summary ───────────────────────────────────────');
        $this->line("  {$prefix}Whitelist adds:    {$counts['adds']}");
        $this->line("  {$prefix}Whitelist removes: {$counts['removes']}");
        $this->line("  {$prefix}Rank changes:      {$counts['rank_changes']}");
        $this->line("  {$prefix}Staff changes:     {$counts['staff_changes']}");

        if (! $dryRun) {
            $this->line("  Failures:          {$counts['failures']}");
        }
    }
}
