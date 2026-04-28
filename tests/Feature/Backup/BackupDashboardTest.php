<?php

declare(strict_types=1);

use App\Jobs\CreateBackupJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
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

it('clicking Create Backup Now dispatches a CreateBackupJob', function () {
    Queue::fake();
    $user = User::factory()->withRole('Backup Manager')->create();

    Volt::actingAs($user)
        ->test('backup.dashboard')
        ->call('createBackup');

    Queue::assertPushed(CreateBackupJob::class);
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
