<?php

declare(strict_types=1);

use App\Services\BackupRetentionService;
use App\Services\BackupStorageService;
use Illuminate\Support\Facades\Storage;

uses()->group('backup', 's3');

beforeEach(function () {
    Storage::fake('s3');
});

// ── BackupStorageService ──────────────────────────────────────────────────────

it('uploads a local backup to S3 under the backups/ prefix', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'backup_').'.sql.gz';
    file_put_contents($tmpFile, 'test content');

    $service = app(BackupStorageService::class);
    $key = $service->upload($tmpFile);

    expect($key)->toStartWith('backups/')
        ->and(Storage::disk('s3')->exists($key))->toBeTrue();

    @unlink($tmpFile);
});

it('lists S3 backups sorted newest-first by filename timestamp', function () {
    Storage::disk('s3')->put('backups/backup_2026-01-01_03-00-00_sqlite.sql.gz', 'a');
    Storage::disk('s3')->put('backups/backup_2026-01-10_03-00-00_sqlite.sql.gz', 'b');
    Storage::disk('s3')->put('backups/backup_2026-01-05_03-00-00_sqlite.sql.gz', 'c');

    $list = app(BackupStorageService::class)->list();

    expect($list[0])->toContain('2026-01-10')
        ->and($list[1])->toContain('2026-01-05')
        ->and($list[2])->toContain('2026-01-01');
});

it('downloads an S3 backup to a temp file', function () {
    Storage::disk('s3')->put('backups/backup_2026-01-01_03-00-00_sqlite.sql.gz', 'gz content');

    $service = app(BackupStorageService::class);
    $tmpPath = $service->download('backups/backup_2026-01-01_03-00-00_sqlite.sql.gz');

    expect(file_exists($tmpPath))->toBeTrue()
        ->and(file_get_contents($tmpPath))->toBe('gz content');

    @unlink($tmpPath);
});

it('deletes an S3 backup by key', function () {
    $key = 'backups/backup_2026-01-01_03-00-00_sqlite.sql.gz';
    Storage::disk('s3')->put($key, 'data');

    app(BackupStorageService::class)->delete($key);

    expect(Storage::disk('s3')->exists($key))->toBeFalse();
});

// ── S3 retention tiers ────────────────────────────────────────────────────────

it('S3 retention keeps 2 most recent, 1 per week for 4 weeks, 1 per month for 3 months', function () {
    // Freeze to the last day of the current month (at noon) so that all 4-week
    // window files land in the current calendar month and month-1 is unambiguously
    // the previous month — prevents false extra deletions when the test runs early
    // in a new month (e.g. May 1 puts "month-1" = April which also holds week files).
    $this->travelTo(now()->endOfMonth()->setTime(12, 0, 0));

    $service = app(BackupStorageService::class);
    $now = now();

    // Tier-1 targets: 2 most recent (days 0 and 3)
    $t1a = 'backups/backup_'.$now->copy()->subDays(0)->format('Y-m-d').'_03-00-00_sqlite.sql.gz';
    $t1b = 'backups/backup_'.$now->copy()->subDays(3)->format('Y-m-d').'_03-00-00_sqlite.sql.gz';

    // Tier-2 targets: 1 per 7-day window
    $w1 = 'backups/backup_'.$now->copy()->subDays(6)->format('Y-m-d').'_03-00-00_sqlite.sql.gz';
    $w2 = 'backups/backup_'.$now->copy()->subDays(10)->format('Y-m-d').'_03-00-00_sqlite.sql.gz';
    $w3 = 'backups/backup_'.$now->copy()->subDays(17)->format('Y-m-d').'_03-00-00_sqlite.sql.gz';
    $w4 = 'backups/backup_'.$now->copy()->subDays(24)->format('Y-m-d').'_03-00-00_sqlite.sql.gz';

    // Tier-3 targets: 1 per calendar month (1, 2, 3 months ago).
    // Anchor to the 1st of the current month to avoid date-of-month overflow
    // (e.g. April 29 - 2 months naively = March 1, not February).
    $m1 = 'backups/backup_'.$now->copy()->startOfMonth()->subMonths(1)->format('Y-m-d').'_03-00-00_sqlite.sql.gz';
    $m2 = 'backups/backup_'.$now->copy()->startOfMonth()->subMonths(2)->format('Y-m-d').'_03-00-00_sqlite.sql.gz';
    $m3 = 'backups/backup_'.$now->copy()->startOfMonth()->subMonths(3)->format('Y-m-d').'_03-00-00_sqlite.sql.gz';

    // Old file that should be deleted (5 months ago)
    $old = 'backups/backup_'.$now->copy()->subMonths(5)->format('Y-m-d').'_03-00-00_sqlite.sql.gz';

    $allKeys = [$t1a, $t1b, $w1, $w2, $w3, $w4, $m1, $m2, $m3, $old];
    foreach ($allKeys as $key) {
        Storage::disk('s3')->put($key, 'data');
    }

    $deleted = app(BackupRetentionService::class)->enforceS3Retention($service);

    expect(Storage::disk('s3')->exists($old))->toBeFalse()
        ->and($deleted)->toContain($old);

    // w1 (day 6) is superseded by t1a (day 0) for the week-1 window; old (5 months) has no tier
    expect(count($deleted))->toBe(2);
});

it('S3 retention returns empty when no files exist', function () {
    $deleted = app(BackupRetentionService::class)->enforceS3Retention(app(BackupStorageService::class));

    expect($deleted)->toBeEmpty();
});

// ── PushBackupToS3 command ────────────────────────────────────────────────────

it('app:backup-push-s3 uploads most recent local backup to S3', function () {
    config(['filesystems.disks.s3.key' => 'fake-key', 'filesystems.disks.s3.bucket' => 'fake-bucket']);

    $dir = storage_path('app/backups');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $path = "{$dir}/backup_".now()->format('Y-m-d_H-i-s').'_sqlite.sql.gz';
    $gz = gzopen($path, 'wb');
    gzwrite($gz, '-- SQLite dump');
    gzclose($gz);

    $this->artisan('app:backup-push-s3')->assertSuccessful();

    $list = app(BackupStorageService::class)->list();
    expect($list)->not->toBeEmpty();

    @unlink($path);
});

it('app:backup-push-s3 fails when no local backups exist', function () {
    foreach (glob(storage_path('app/backups/*.sql.gz')) ?: [] as $f) {
        @unlink($f);
    }

    $this->artisan('app:backup-push-s3')->assertFailed();
});

it('app:backup-push-s3 is scheduled every 3 days at 03:30', function () {
    $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command ?? '', 'backup-push-s3'));

    expect($events->isNotEmpty())->toBeTrue()
        ->and($events->first()->expression)->toBe('30 3 */3 * *');
});

// ── isConfigured / isConnected ────────────────────────────────────────────────

it('isConfigured returns true when S3 credentials are set', function () {
    config(['filesystems.disks.s3.key' => 'fake-key', 'filesystems.disks.s3.bucket' => 'fake-bucket']);

    expect(app(BackupStorageService::class)->isConfigured())->toBeTrue();
});

it('isConfigured returns false when S3 key is missing', function () {
    config(['filesystems.disks.s3.key' => null, 'filesystems.disks.s3.bucket' => 'fake-bucket']);

    expect(app(BackupStorageService::class)->isConfigured())->toBeFalse();
});

it('isConnected returns true when S3 is reachable', function () {
    config(['filesystems.disks.s3.key' => 'fake-key', 'filesystems.disks.s3.bucket' => 'fake-bucket']);

    // Storage::fake('s3') is set up in beforeEach; a successful list() call means connected
    expect(app(BackupStorageService::class)->isConnected())->toBeTrue();
});

it('isConnected returns false when S3 is not configured', function () {
    config(['filesystems.disks.s3.key' => null, 'filesystems.disks.s3.bucket' => null]);

    expect(app(BackupStorageService::class)->isConnected())->toBeFalse();
});

// ── listWithMetadata ──────────────────────────────────────────────────────────

it('listWithMetadata returns filename, size, and date for each S3 backup', function () {
    Storage::disk('s3')->put('backups/backup_2026-03-01_03-00-00_sqlite.sql.gz', 'gz content');

    $list = app(BackupStorageService::class)->listWithMetadata();

    expect($list)->toHaveCount(1)
        ->and($list[0]['filename'])->toBe('backup_2026-03-01_03-00-00_sqlite.sql.gz')
        ->and($list[0]['key'])->toBe('backups/backup_2026-03-01_03-00-00_sqlite.sql.gz')
        ->and($list[0])->toHaveKeys(['filename', 'key', 'size', 'date']);
});

it('listWithMetadata returns newest-first ordering', function () {
    Storage::disk('s3')->put('backups/backup_2026-01-01_03-00-00_sqlite.sql.gz', 'a');
    Storage::disk('s3')->put('backups/backup_2026-03-01_03-00-00_sqlite.sql.gz', 'b');

    $list = app(BackupStorageService::class)->listWithMetadata();

    expect($list[0]['filename'])->toContain('2026-03-01')
        ->and($list[1]['filename'])->toContain('2026-01-01');
});
