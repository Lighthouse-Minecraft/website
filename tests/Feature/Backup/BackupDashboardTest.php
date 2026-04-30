<?php

declare(strict_types=1);

use App\Jobs\RestoreBackupJob;
use App\Models\SiteConfig;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

uses()->group('backup', 'dashboard');

function makeLocalBackup(string $filename): string
{
    $dir = storage_path('app/backups');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = "{$dir}/{$filename}";
    $gz = gzopen($path, 'wb');
    gzwrite($gz, '-- SQLite dump');
    gzclose($gz);

    return $path;
}

afterEach(function () {
    foreach (glob(storage_path('app/backups/test_dash_*.sql.gz')) as $file) {
        @unlink($file);
    }
});

// ── Authorization ─────────────────────────────────────────────────────────────

it('unauthenticated users are redirected from the backup dashboard', function () {
    $this->get(route('backups.index'))->assertRedirect(route('login'));
});

it('users without backup-manager role get 403 on the backup dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('backups.index'))
        ->assertForbidden();
});

it('backup manager can access the backup dashboard', function () {
    $user = User::factory()->withRole('Backup Manager')->create();

    $this->actingAs($user)
        ->get(route('backups.index'))
        ->assertOk();
});

// ── Ready Room link ───────────────────────────────────────────────────────────

it('ready room header shows Backups link only for backup managers', function () {
    // Backup Manager also needs Staff Access to pass the ready-room controller gate
    $manager = User::factory()->withRole('Staff Access')->withRole('Backup Manager')->create();
    $staff = User::factory()->withRole('Staff Access')->create();

    $this->actingAs($manager)
        ->get(route('ready-room.index'))
        ->assertSee('Backups');

    $this->actingAs($staff)
        ->get(route('ready-room.index'))
        ->assertDontSee(route('backups.index'));
});

// ── Local backup list ─────────────────────────────────────────────────────────

it('dashboard lists local backup files with metadata', function () {
    $user = User::factory()->withRole('Backup Manager')->create();
    makeLocalBackup('test_dash_backup_2026-04-01_03-00-00_sqlite.sql.gz');

    $this->actingAs($user)
        ->get(route('backups.index'))
        ->assertSee('test_dash_backup_2026-04-01_03-00-00_sqlite.sql.gz')
        ->assertSee('sqlite');
});

// ── Create Backup ─────────────────────────────────────────────────────────────

it('clicking Create Backup Now runs the backup synchronously and shows the file', function () {
    $user = User::factory()->withRole('Backup Manager')->create();

    $fakePath = storage_path('app/backups/backup_2026-04-01_03-00-00_sqlite.sql.gz');
    $mock = Mockery::mock(BackupService::class);
    $mock->shouldReceive('create')->once()->andReturn($fakePath);
    app()->instance(BackupService::class, $mock);

    Volt::actingAs($user)
        ->test('backup.dashboard')
        ->call('createBackup')
        ->assertHasNoErrors();

    expect(SiteConfig::getValue('backup.last_job_status'))->toBe('completed');
    expect(SiteConfig::getValue('backup.last_job_filename'))->not->toBeNull();
});

// ── Delete ────────────────────────────────────────────────────────────────────

it('deleting a backup removes it from disk', function () {
    $user = User::factory()->withRole('Backup Manager')->create();
    $path = makeLocalBackup('test_dash_delete_2026-04-01_03-00-00_sqlite.sql.gz');

    Volt::actingAs($user)
        ->test('backup.dashboard')
        ->set('deleteTarget', 'test_dash_delete_2026-04-01_03-00-00_sqlite.sql.gz')
        ->call('deleteBackup');

    expect(file_exists($path))->toBeFalse();
});

it('non-backup-manager is blocked at the route level', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('backups.index'))
        ->assertForbidden();
});

// ── Restore ───────────────────────────────────────────────────────────────────

it('restore action dispatches RestoreBackupJob', function () {
    Queue::fake();
    $user = User::factory()->withRole('Backup Manager')->create();
    makeLocalBackup('test_dash_restore_2026-04-01_03-00-00_sqlite.sql.gz');

    Volt::actingAs($user)
        ->test('backup.dashboard')
        ->set('restoreTarget', 'test_dash_restore_2026-04-01_03-00-00_sqlite.sql.gz')
        ->call('restoreBackup');

    Queue::assertPushed(RestoreBackupJob::class);
});

it('confirmRestore sets restoreTarget', function () {
    $user = User::factory()->withRole('Backup Manager')->create();
    makeLocalBackup('test_dash_confirm_2026-04-01_03-00-00_sqlite.sql.gz');

    Volt::actingAs($user)
        ->test('backup.dashboard')
        ->call('confirmRestore', 'test_dash_confirm_2026-04-01_03-00-00_sqlite.sql.gz')
        ->assertSet('restoreTarget', 'test_dash_confirm_2026-04-01_03-00-00_sqlite.sql.gz');
});

// ── Upload ────────────────────────────────────────────────────────────────────

it('uploading a valid .sql.gz file places it in storage', function () {
    $user = User::factory()->withRole('Backup Manager')->create();

    $gzContent = gzencode('-- SQL dump');
    $file = UploadedFile::fake()->createWithContent('test_dash_upload_2026-04-01_03-00-00_sqlite.sql.gz', $gzContent);

    Volt::actingAs($user)
        ->test('backup.dashboard')
        ->set('uploadFile', $file)
        ->call('uploadBackup')
        ->assertHasNoErrors();

    $dest = storage_path('app/backups/test_dash_upload_2026-04-01_03-00-00_sqlite.sql.gz');
    expect(file_exists($dest))->toBeTrue();
});

it('uploading a non-.sql.gz file is rejected', function () {
    $user = User::factory()->withRole('Backup Manager')->create();

    $gzContent = gzencode('bad file');
    $file = UploadedFile::fake()->createWithContent('test_dash_upload_bad.tar.gz', $gzContent);

    Volt::actingAs($user)
        ->test('backup.dashboard')
        ->set('uploadFile', $file)
        ->call('uploadBackup')
        ->assertHasErrors(['uploadFile']);
});

// ── Settings ──────────────────────────────────────────────────────────────────

it('toggling offlineDuringBackup persists to SiteConfig', function () {
    $user = User::factory()->withRole('Backup Manager')->create();

    SiteConfig::setValue('backup.offline_during_backup', 'false');

    Volt::actingAs($user)
        ->test('backup.dashboard')
        ->set('offlineDuringBackup', true);

    expect(SiteConfig::getValue('backup.offline_during_backup', 'false'))->toBe('true');
});

it('toggling offlineDuringRestore persists to SiteConfig', function () {
    $user = User::factory()->withRole('Backup Manager')->create();

    SiteConfig::setValue('backup.offline_during_restore', 'true');

    Volt::actingAs($user)
        ->test('backup.dashboard')
        ->set('offlineDuringRestore', false);

    expect(SiteConfig::getValue('backup.offline_during_restore', 'true'))->toBe('false');
});

it('mount loads SiteConfig values into toggle properties', function () {
    $user = User::factory()->withRole('Backup Manager')->create();

    SiteConfig::setValue('backup.offline_during_backup', 'true');
    SiteConfig::setValue('backup.offline_during_restore', 'false');

    Volt::actingAs($user)
        ->test('backup.dashboard')
        ->assertSet('offlineDuringBackup', true)
        ->assertSet('offlineDuringRestore', false);
});

// ── S3 Panel ──────────────────────────────────────────────────────────────────

it('dashboard shows S3 not configured when credentials are missing', function () {
    config(['filesystems.disks.s3.key' => null]);

    $user = User::factory()->withRole('Backup Manager')->create();

    $this->actingAs($user)
        ->get(route('backups.index'))
        ->assertSee('S3 Not Configured');
});

it('dashboard shows S3 connected when S3 is reachable', function () {
    Storage::fake('s3');
    config(['filesystems.disks.s3.key' => 'fake-key', 'filesystems.disks.s3.bucket' => 'fake-bucket']);

    $user = User::factory()->withRole('Backup Manager')->create();

    $this->actingAs($user)
        ->get(route('backups.index'))
        ->assertSee('S3 Connected');
});

it('dashboard lists S3 backup files in the S3 panel', function () {
    Storage::fake('s3');
    config(['filesystems.disks.s3.key' => 'fake-key', 'filesystems.disks.s3.bucket' => 'fake-bucket']);
    Storage::disk('s3')->put('backups/backup_2026-04-01_03-00-00_sqlite.sql.gz', 'gz content');

    $user = User::factory()->withRole('Backup Manager')->create();

    $this->actingAs($user)
        ->get(route('backups.index'))
        ->assertSee('backup_2026-04-01_03-00-00_sqlite.sql.gz');
});

it('deleteS3Backup removes file from S3', function () {
    Storage::fake('s3');
    config(['filesystems.disks.s3.key' => 'fake-key', 'filesystems.disks.s3.bucket' => 'fake-bucket']);
    Storage::disk('s3')->put('backups/backup_2026-04-01_03-00-00_sqlite.sql.gz', 'gz content');

    $user = User::factory()->withRole('Backup Manager')->create();

    Volt::actingAs($user)
        ->test('backup.dashboard')
        ->set('deleteS3Target', 'backup_2026-04-01_03-00-00_sqlite.sql.gz')
        ->call('deleteS3Backup');

    Storage::disk('s3')->assertMissing('backups/backup_2026-04-01_03-00-00_sqlite.sql.gz');
});

// ── Storage Stats ─────────────────────────────────────────────────────────────

it('dashboard storage stats panel shows known asset directories', function () {
    $user = User::factory()->withRole('Backup Manager')->create();

    $this->actingAs($user)
        ->get(route('backups.index'))
        ->assertSee('Staff Photos')
        ->assertSee('Message Images')
        ->assertSee('Community Stories');
});
