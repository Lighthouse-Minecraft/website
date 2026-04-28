<?php

declare(strict_types=1);

use App\Models\SiteConfig;
use App\Models\User;
use App\Notifications\RestoreCompletedNotification;
use App\Notifications\RestoreFailedNotification;
use App\Services\RestoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;

uses()->group('backup', 'commands');

function makeBackupFile(string $filename, string $sql = '-- SQLite dump'): string
{
    $dir = storage_path('app/backups');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = "{$dir}/{$filename}";
    $gz = gzopen($path, 'wb');
    gzwrite($gz, $sql);
    gzclose($gz);

    return $path;
}

afterEach(function () {
    foreach (glob(storage_path('app/backups/*.sql.gz')) as $file) {
        @unlink($file);
    }
    if (app()->isDownForMaintenance()) {
        \Illuminate\Support\Facades\Artisan::call('up');
    }
});

it('restores a backup and results in expected database state', function () {
    $tmpDb = sys_get_temp_dir().'/restore_test_'.uniqid().'.sqlite';
    $gzFile = storage_path('app/backups/restore_state_test_2026-01-01_00-00-00_sqlite.sql.gz');

    try {
        // Create the file first so Laravel's SQLite connector can find it.
        touch($tmpDb);

        // Register a named connection for the temp file so we don't touch the :memory: test DB.
        config(['database.connections.restore_test' => [
            'driver' => 'sqlite',
            'database' => $tmpDb,
            'foreign_key_constraints' => false,
        ]]);

        // Build the pre-restore state: one row to keep, one marker to remove.
        // Also include site_configs so SiteConfig::getValue can query it after we switch default.
        $pdo = DB::connection('restore_test')->getPdo();
        $pdo->exec('CREATE TABLE "site_configs" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "key" TEXT, "value" TEXT, "description" TEXT, "created_at" TEXT, "updated_at" TEXT)');
        $pdo->exec("INSERT INTO \"site_configs\" (\"key\", \"value\") VALUES ('backup.offline_during_restore', 'false')");
        $pdo->exec('CREATE TABLE "items" ("id" INTEGER PRIMARY KEY, "name" TEXT NOT NULL)');
        $pdo->exec("INSERT INTO \"items\" VALUES (1, 'kept')");
        $pdo->exec("INSERT INTO \"items\" VALUES (2, 'to_be_removed')");

        // The dump represents the backup taken before the marker was inserted.
        $dumpSql = "CREATE TABLE \"items\" (\"id\" INTEGER PRIMARY KEY, \"name\" TEXT NOT NULL);\n"
            ."INSERT INTO \"items\" (\"id\", \"name\") VALUES (1, 'kept');\n";
        $gz = gzopen($gzFile, 'wb');
        gzwrite($gz, $dumpSql);
        gzclose($gz);

        // Switch default connection to the temp file for the duration of the restore.
        config(['database.default' => 'restore_test']);

        $restoreService = new RestoreService;
        $restoreService->restore($gzFile);

        $count = (int) DB::connection('restore_test')->getPdo()
            ->query("SELECT COUNT(*) FROM \"items\" WHERE name='to_be_removed'")
            ->fetchColumn();
        expect($count)->toBe(0);

    } finally {
        config(['database.default' => 'sqlite']);
        DB::purge('restore_test');
        @unlink($tmpDb);
        @unlink($gzFile);
    }
});

it('aborts cross-type restore with clear error when pgloader is not installed', function () {
    Process::fake([
        'which pgloader' => Process::result('', '', 1),
    ]);

    // pgsql-typed backup on a sqlite target — cross-type
    makeBackupFile('backup_2026-01-01_00-00-00_pgsql.sql.gz', '-- PostgreSQL dump');

    $this->artisan('app:backup-restore', ['filename' => 'backup_2026-01-01_00-00-00_pgsql.sql.gz'])
        ->assertFailed()
        ->expectsOutputToContain('pgloader');
});

it('enters and exits maintenance mode during restore when offline_during_restore is true', function () {
    SiteConfig::setValue('backup.offline_during_restore', 'true');

    // Mock so the restore throws, ensuring the up/down flow is exercised and
    // the site comes back online even on failure
    $service = $this->mock(RestoreService::class);
    $service->shouldReceive('restore')->andThrow(new \RuntimeException('Simulated failure'));

    makeBackupFile('backup_2026-01-01_00-00-00_sqlite.sql.gz');

    $this->artisan('app:backup-restore', ['filename' => 'backup_2026-01-01_00-00-00_sqlite.sql.gz'])
        ->assertFailed();

    expect(app()->isDownForMaintenance())->toBeFalse();
});

it('notifies backup managers on successful restore', function () {
    $manager = User::factory()->withRole('Backup Manager')->create();

    // Mock RestoreService so tables are never dropped — notification queries stay intact
    $this->mock(RestoreService::class)
        ->shouldReceive('restore')
        ->once();

    makeBackupFile('backup_2026-01-01_00-00-00_sqlite.sql.gz');

    $this->artisan('app:backup-restore', ['filename' => 'backup_2026-01-01_00-00-00_sqlite.sql.gz'])
        ->assertSuccessful();

    Notification::assertSentTo($manager, RestoreCompletedNotification::class);
});

it('notifies backup managers on restore failure', function () {
    $manager = User::factory()->withRole('Backup Manager')->create();

    $this->mock(RestoreService::class)
        ->shouldReceive('restore')
        ->andThrow(new \RuntimeException('Simulated restore failure'));

    makeBackupFile('backup_2026-01-01_00-00-00_sqlite.sql.gz');

    $this->artisan('app:backup-restore', ['filename' => 'backup_2026-01-01_00-00-00_sqlite.sql.gz'])
        ->assertFailed();

    Notification::assertSentTo($manager, RestoreFailedNotification::class);
});
