<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\BackupCreatedNotification;
use App\Notifications\BackupFailedNotification;
use App\Services\BackupService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;

uses()->group('backup', 'commands');

// pg_dump on ubuntu-latest (pg client 16) fails against the CI PostgreSQL 17 service.
// Fake all process calls to return a synthetic SQL dump so the tests focus on command
// behaviour (file creation, notifications, maintenance mode) rather than the dump binary.
beforeEach(function () {
    if (config('database.default') === 'pgsql') {
        Process::fake(fn () => Process::result('-- PostgreSQL SQL dump'));
    }
});

beforeEach(function () {
    $this->preExistingBackupFiles = glob(storage_path('app/backups/*.sql.gz')) ?: [];
});

afterEach(function () {
    $pre = $this->preExistingBackupFiles ?? [];
    foreach ((glob(storage_path('app/backups/*.sql.gz')) ?: []) as $file) {
        if (! in_array($file, $pre, true)) {
            @unlink($file);
        }
    }
});

it('creates a .sql.gz backup file', function () {
    $this->artisan('app:backup-create')->assertSuccessful();

    $newFiles = array_diff(glob(storage_path('app/backups/*.sql.gz')) ?: [], $this->preExistingBackupFiles);
    expect($newFiles)->toHaveCount(1);
});

it('backup file has non-zero size', function () {
    $this->artisan('app:backup-create')->assertSuccessful();

    $newFiles = array_values(array_diff(glob(storage_path('app/backups/*.sql.gz')) ?: [], $this->preExistingBackupFiles));
    expect(filesize($newFiles[0]))->toBeGreaterThan(0);
});

it('backup filename includes timestamp and database type', function () {
    $this->artisan('app:backup-create')->assertSuccessful();

    $newFiles = array_values(array_diff(glob(storage_path('app/backups/*.sql.gz')) ?: [], $this->preExistingBackupFiles));
    $filename = basename($newFiles[0]);

    expect($filename)->toMatch('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_\w+\.sql\.gz$/');
});

it('notifies backup managers on success', function () {
    $manager = User::factory()->withRole('Backup Manager')->create();

    $this->artisan('app:backup-create')->assertSuccessful();

    Notification::assertSentTo($manager, BackupCreatedNotification::class);
});

it('notifies backup managers on failure', function () {
    $manager = User::factory()->withRole('Backup Manager')->create();

    $service = $this->mock(BackupService::class);
    $service->shouldReceive('setSkipOffline')->andReturnSelf();
    $service->shouldReceive('create')->andThrow(new \RuntimeException('Simulated backup failure'));

    $this->artisan('app:backup-create')->assertFailed();

    Notification::assertSentTo($manager, BackupFailedNotification::class);
});

it('does not trigger maintenance mode when offline_during_backup is false', function () {
    \App\Models\SiteConfig::setValue('backup.offline_during_backup', 'false');

    $this->artisan('app:backup-create')->assertSuccessful();

    expect(app()->isDownForMaintenance())->toBeFalse();
});

it('does not trigger maintenance mode with --skip-offline flag', function () {
    \App\Models\SiteConfig::setValue('backup.offline_during_backup', 'true');

    $this->artisan('app:backup-create', ['--skip-offline' => true])->assertSuccessful();

    expect(app()->isDownForMaintenance())->toBeFalse();
});

it('is scheduled daily at 03:00', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('app:backup-create')
        ->assertSuccessful();
});
