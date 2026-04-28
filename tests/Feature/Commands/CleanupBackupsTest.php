<?php

declare(strict_types=1);

use App\Models\SiteConfig;
use App\Services\BackupRetentionService;

uses()->group('backup', 'commands');

function makeAgedBackupFile(string $filename, int $daysOld): string
{
    $dir = storage_path('app/backups');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = "{$dir}/{$filename}";
    $gz = gzopen($path, 'wb');
    gzwrite($gz, '-- SQLite dump');
    gzclose($gz);

    $mtime = now()->subDays($daysOld)->timestamp;
    touch($path, $mtime);

    return $path;
}

afterEach(function () {
    foreach (glob(storage_path('app/backups/cleanup_test_*.sql.gz')) as $file) {
        @unlink($file);
    }
});

it('deletes files older than the retention window and keeps newer files', function () {
    SiteConfig::setValue('backup.local_retention_days', '7');

    $old = makeAgedBackupFile('cleanup_test_old_sqlite.sql.gz', 10);
    $edge = makeAgedBackupFile('cleanup_test_edge_sqlite.sql.gz', 7);
    $new = makeAgedBackupFile('cleanup_test_new_sqlite.sql.gz', 3);

    $service = app(BackupRetentionService::class);
    $deleted = $service->enforceLocalRetention();

    expect(file_exists($old))->toBeFalse()
        ->and(file_exists($edge))->toBeFalse()
        ->and(file_exists($new))->toBeTrue()
        ->and(count($deleted))->toBe(2);
});

it('respects a custom retention window from SiteConfig', function () {
    SiteConfig::setValue('backup.local_retention_days', '14');

    $old = makeAgedBackupFile('cleanup_test_14old_sqlite.sql.gz', 15);
    $new = makeAgedBackupFile('cleanup_test_14new_sqlite.sql.gz', 5);

    $service = app(BackupRetentionService::class);
    $service->enforceLocalRetention();

    expect(file_exists($old))->toBeFalse()
        ->and(file_exists($new))->toBeTrue();
});

it('returns an empty array when no files need to be deleted', function () {
    SiteConfig::setValue('backup.local_retention_days', '7');
    makeAgedBackupFile('cleanup_test_recent_sqlite.sql.gz', 2);

    $deleted = app(BackupRetentionService::class)->enforceLocalRetention();

    expect($deleted)->toBeEmpty();
});

it('the app:backup-cleanup command exits successfully', function () {
    SiteConfig::setValue('backup.local_retention_days', '7');
    makeAgedBackupFile('cleanup_test_cmd_sqlite.sql.gz', 10);

    $this->artisan('app:backup-cleanup')->assertSuccessful();
});

it('app:backup-cleanup is scheduled daily at 04:00', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('app:backup-cleanup')
        ->expectsOutputToContain('0   4 * * *');
});
