<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateFilesToS3 extends Command
{
    protected $signature = 'app:migrate-files-to-s3
                            {--dry-run : List files that would be migrated without actually uploading}
                            {--directory= : Migrate only a specific directory (e.g. staff-photos)}';

    protected $description = 'Migrate files from local public disk to S3. Use when switching from local to S3 storage.';

    public function handle(): int
    {
        $localDisk = Storage::disk('public');
        $s3Disk = Storage::disk('s3');
        $dryRun = $this->option('dry-run');
        $specificDir = $this->option('directory');

        $directories = $specificDir
            ? [$specificDir]
            : ['staff-photos', 'board-member-photos', 'community-stories'];

        if ($dryRun) {
            $this->info('DRY RUN — no files will be uploaded.');
        }

        $this->info('Migrating files from local public disk to S3...');

        $totalMigrated = 0;
        $totalSkipped = 0;
        $totalFailed = 0;

        foreach ($directories as $directory) {
            $files = $localDisk->files($directory);

            if (empty($files)) {
                $this->line("  [{$directory}] No files found.");

                continue;
            }

            $this->info("  [{$directory}] Found ".count($files).' file(s).');

            foreach ($files as $filePath) {
                if ($s3Disk->exists($filePath)) {
                    $this->line("    SKIP (already on S3): {$filePath}");
                    $totalSkipped++;

                    continue;
                }

                if ($dryRun) {
                    $size = $localDisk->size($filePath);
                    $this->line("    WOULD MIGRATE: {$filePath} (".number_format($size / 1024, 1).' KB)');
                    $totalMigrated++;

                    continue;
                }

                try {
                    $contents = $localDisk->get($filePath);
                    $s3Disk->put($filePath, $contents);
                    $totalMigrated++;
                    $this->line("    MIGRATED: {$filePath}");
                } catch (\Exception $e) {
                    $totalFailed++;
                    $this->error("    FAILED: {$filePath} — {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $verb = $dryRun ? 'Would migrate' : 'Migrated';
        $this->info("{$verb}: {$totalMigrated}, Skipped: {$totalSkipped}, Failed: {$totalFailed}");

        if ($totalFailed > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
