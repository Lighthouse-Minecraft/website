<?php

use App\Jobs\RestoreBackupJob;
use App\Models\SiteConfig;
use App\Services\BackupStorageService;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    use WithFileUploads;

    public ?string $deleteTarget = null;

    public ?string $restoreTarget = null;

    public ?string $deleteS3Target = null;

    // Settings
    public bool $offlineDuringBackup = false;

    public bool $offlineDuringRestore = true;

    // Upload
    public $uploadFile = null;

    public function mount(): void
    {
        $this->authorize('backup-manager');
        $this->offlineDuringBackup = SiteConfig::getValue('backup.offline_during_backup', 'false') === 'true';
        $this->offlineDuringRestore = SiteConfig::getValue('backup.offline_during_restore', 'true') === 'true';
    }

    private function validateBackupFilename(string $filename): string
    {
        abort_unless(
            preg_match('/\A[a-zA-Z0-9._-]+\.sql\.gz\z/', $filename) === 1,
            404
        );

        return $filename;
    }

    #[\Livewire\Attributes\Computed]
    public function localBackups(): array
    {
        $dir = storage_path('app/backups');
        $files = glob("{$dir}/*.sql.gz") ?: [];

        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

        return array_map(function ($path) {
            $filename = basename($path);
            preg_match('/_(pgsql|mysql|sqlite)\.sql\.gz$/', $filename, $m);

            return [
                'filename' => $filename,
                'size' => $this->formatBytes(filesize($path)),
                'date' => \Carbon\Carbon::createFromTimestamp(filemtime($path))->format('Y-m-d H:i'),
                'db_type' => $m[1] ?? 'unknown',
            ];
        }, $files);
    }

    #[\Livewire\Attributes\Computed]
    public function s3Configured(): bool
    {
        return app(BackupStorageService::class)->isConfigured();
    }

    #[\Livewire\Attributes\Computed]
    public function s3Connected(): bool
    {
        return app(BackupStorageService::class)->isConnected();
    }

    #[\Livewire\Attributes\Computed]
    public function s3Backups(): array
    {
        if (! $this->s3Connected) {
            return [];
        }

        return app(BackupStorageService::class)->listWithMetadata();
    }

    #[\Livewire\Attributes\Computed]
    public function storageStats(): array
    {
        $directories = [
            'Staff Photos' => 'staff-photos',
            'Board Member Photos' => 'board-member-photos',
            'Community Stories' => 'community-stories',
            'Message Images' => 'message-images',
            'Report Evidence' => 'report-evidence',
            'Blog Images' => 'blog/images',
            'Blog Category Hero' => 'blog/category-hero',
        ];

        $s3Available = $this->s3Connected;
        $stats = [];

        foreach ($directories as $label => $dir) {
            $localFiles = Storage::disk('public')->allFiles($dir);
            $localCount = count($localFiles);
            $localSize = 0;

            foreach ($localFiles as $file) {
                $localSize += Storage::disk('public')->size($file);
            }

            $s3Count = null;
            $s3Size = null;

            if ($s3Available) {
                $s3Files = Storage::disk('s3')->allFiles($dir);
                $s3Count = count($s3Files);
                $raw = 0;
                foreach ($s3Files as $file) {
                    $raw += Storage::disk('s3')->size($file);
                }
                $s3Size = $this->formatBytes($raw);
            }

            $stats[] = [
                'label' => $label,
                'directory' => $dir,
                'local_count' => $localCount,
                'local_size' => $this->formatBytes($localSize),
                's3_count' => $s3Count,
                's3_size' => $s3Size,
            ];
        }

        return $stats;
    }

    #[\Livewire\Attributes\Computed]
    public function backupJobStatus(): array
    {
        $status = SiteConfig::getValue('backup.last_job_status');
        $updatedAt = SiteConfig::getValue('backup.last_job_updated_at');
        $filename = SiteConfig::getValue('backup.last_job_filename');
        $fullPath = SiteConfig::getValue('backup.last_job_full_path');

        // Auto-expire the completed badge after 60 seconds.
        if ($status === 'completed' && $updatedAt && now()->diffInSeconds(\Carbon\Carbon::parse($updatedAt)) > 60) {
            $status = null;
        }

        $scanDir = storage_path('app/backups');
        $scanDirExists = is_dir($scanDir);
        $scanContents = $scanDirExists ? (scandir($scanDir) ?: []) : [];
        $scanContents = array_values(array_filter($scanContents, fn ($f) => ! in_array($f, ['.', '..'])));

        return [
            'status' => $status,
            'updated_at' => $updatedAt ? \Carbon\Carbon::parse($updatedAt)->diffForHumans() : null,
            'filename' => $filename,
            'full_path' => $fullPath,
            'path_exists' => $fullPath ? file_exists($fullPath) : null,
            'scan_dir' => $scanDir,
            'scan_dir_exists' => $scanDirExists,
            'scan_dir_contents' => $scanDirExists ? (count($scanContents) > 0 ? implode(', ', $scanContents) : '(empty)') : 'N/A',
            'open_basedir' => ini_get('open_basedir'),
        ];
    }

    public function dismissJobStatus(): void
    {
        $this->authorize('backup-manager');
        SiteConfig::setValue('backup.last_job_status', null);
        SiteConfig::setValue('backup.last_job_updated_at', null);
        SiteConfig::setValue('backup.last_job_filename', null);
        SiteConfig::setValue('backup.last_job_full_path', null);
        unset($this->backupJobStatus);
    }

    public function createBackup(): void
    {
        $this->authorize('backup-manager');

        SiteConfig::setValue('backup.last_job_status', 'running');
        SiteConfig::setValue('backup.last_job_updated_at', now()->toIso8601String());
        SiteConfig::setValue('backup.last_job_filename', null);
        SiteConfig::setValue('backup.last_job_full_path', null);

        try {
            $path = app(\App\Services\BackupService::class)->create();
            SiteConfig::setValue('backup.last_job_status', 'completed');
            SiteConfig::setValue('backup.last_job_updated_at', now()->toIso8601String());
            SiteConfig::setValue('backup.last_job_filename', basename($path));
            SiteConfig::setValue('backup.last_job_full_path', $path);
            unset($this->localBackups);
            Flux::toast('Backup created successfully.', 'Success', variant: 'success');
        } catch (\Throwable $e) {
            SiteConfig::setValue('backup.last_job_status', 'failed');
            SiteConfig::setValue('backup.last_job_updated_at', now()->toIso8601String());
            Flux::toast('Backup failed: '.$e->getMessage(), 'Error', variant: 'danger');
        }
    }

    public function uploadBackup(): void
    {
        $this->authorize('backup-manager');

        $this->validate([
            'uploadFile' => ['required', 'file', 'mimes:gz', 'max:524288'], // 512 MB
        ]);

        $originalName = basename($this->uploadFile->getClientOriginalName());

        if (! str_ends_with($originalName, '.sql.gz') || preg_match('/\A[a-zA-Z0-9._-]+\.sql\.gz\z/', $originalName) !== 1) {
            $this->addError('uploadFile', 'File must be a .sql.gz backup file.');

            return;
        }

        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $src = $this->uploadFile->getRealPath();
        $dest = "{$dir}/{$originalName}";

        if (! copy($src, $dest)) {
            $this->addError('uploadFile', 'Failed to save the uploaded file. Check storage permissions.');

            return;
        }

        @chmod($dest, 0644);
        $this->uploadFile = null;
        unset($this->localBackups);
        Flux::toast("Uploaded {$originalName}.", 'Done', variant: 'success');
    }

    public function download(string $filename): StreamedResponse
    {
        $this->authorize('backup-manager');
        $filename = $this->validateBackupFilename($filename);

        $path = storage_path("app/backups/{$filename}");
        abort_if(! file_exists($path), 404);

        return response()->streamDownload(function () use ($path) {
            readfile($path);
        }, $filename, ['Content-Type' => 'application/gzip']);
    }

    public function downloadFromS3(string $filename): StreamedResponse
    {
        $this->authorize('backup-manager');
        $filename = $this->validateBackupFilename($filename);

        $key = "backups/{$filename}";

        return response()->streamDownload(function () use ($key) {
            $stream = Storage::disk('s3')->readStream($key);
            if ($stream === null) {
                abort(404);
            }
            fpassthru($stream);
            fclose($stream);
        }, $filename, ['Content-Type' => 'application/gzip']);
    }

    public function confirmRestore(string $filename): void
    {
        $this->restoreTarget = $this->validateBackupFilename($filename);
        Flux::modal('confirm-restore')->show();
    }

    public function restoreBackup(): void
    {
        $this->authorize('backup-manager');

        if (app()->environment('production')) {
            Flux::toast('Restore is disabled in production. Set APP_ENV=backup-restore in .env to enable an emergency restore.', 'Blocked', variant: 'danger');

            return;
        }

        if ($this->restoreTarget === null) {
            return;
        }

        $this->validateBackupFilename($this->restoreTarget);
        $path = storage_path("app/backups/{$this->restoreTarget}");
        abort_if(! file_exists($path), 404);

        $lock = Cache::lock('restore:'.$this->restoreTarget, 30);
        if (! $lock->get()) {
            Flux::toast('A restore is already queued for this file.', 'Notice', variant: 'warning');

            return;
        }

        RestoreBackupJob::dispatch($path);
        $this->restoreTarget = null;
        Flux::modal('confirm-restore')->close();
        Flux::toast('Restore job queued.', 'Success', variant: 'success');
    }

    public function confirmDelete(string $filename): void
    {
        $this->deleteTarget = $this->validateBackupFilename($filename);
        Flux::modal('confirm-delete')->show();
    }

    public function deleteBackup(): void
    {
        $this->authorize('backup-manager');

        if ($this->deleteTarget === null) {
            return;
        }

        $this->validateBackupFilename($this->deleteTarget);
        $path = storage_path("app/backups/{$this->deleteTarget}");
        if (file_exists($path) && ! unlink($path)) {
            Flux::toast('Backup could not be deleted. Check storage permissions.', 'Error', variant: 'danger');

            return;
        }

        $this->deleteTarget = null;
        Flux::modal('confirm-delete')->close();
        Flux::toast('Backup deleted.', 'Done', variant: 'success');
        unset($this->localBackups);
    }

    public function confirmDeleteS3(string $filename): void
    {
        $this->deleteS3Target = $this->validateBackupFilename($filename);
        Flux::modal('confirm-delete-s3')->show();
    }

    public function deleteS3Backup(): void
    {
        $this->authorize('backup-manager');

        if ($this->deleteS3Target === null) {
            return;
        }

        $this->validateBackupFilename($this->deleteS3Target);
        app(BackupStorageService::class)->delete("backups/{$this->deleteS3Target}");

        $this->deleteS3Target = null;
        Flux::modal('confirm-delete-s3')->close();
        Flux::toast('S3 backup deleted.', 'Done', variant: 'success');
        unset($this->s3Backups);
    }

    public function updatedOfflineDuringBackup(bool $value): void
    {
        $this->authorize('backup-manager');
        SiteConfig::setValue('backup.offline_during_backup', $value ? 'true' : 'false');
    }

    public function updatedOfflineDuringRestore(bool $value): void
    {
        $this->authorize('backup-manager');
        SiteConfig::setValue('backup.offline_during_restore', $value ? 'true' : 'false');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 1).' MB';
        }

        if ($bytes >= 1_024) {
            return number_format($bytes / 1_024, 1).' KB';
        }

        return $bytes.' B';
    }
}; ?>

<div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl">Backup Management</flux:heading>
        <flux:button href="{{ route('ready-room.index') }}" wire:navigate icon="arrow-left">
            Back to Ready Room
        </flux:button>
    </div>

    {{-- Poll while a job is in-flight, one extra cycle to refresh the file list, or every 30s while the completed badge is still showing --}}
    @php $jobStatus = $this->backupJobStatus; @endphp
    @if (in_array($jobStatus['status'], ['queued', 'running']) || ($jobStatus['status'] === 'completed' && ! collect($this->localBackups)->contains('filename', $jobStatus['filename'])))
        <div wire:poll.3s></div>
    @elseif ($jobStatus['status'] === 'completed')
        <div wire:poll.30s></div>
    @endif

    {{-- Local Backups Panel --}}
    <flux:card class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <flux:heading size="lg">Local Backups</flux:heading>
                @if ($jobStatus['status'] === 'queued')
                    <flux:badge color="yellow" icon="clock">Queued{{ $jobStatus['updated_at'] ? ' · '.$jobStatus['updated_at'] : '' }}</flux:badge>
                @elseif ($jobStatus['status'] === 'running')
                    <flux:badge color="blue" icon="arrow-path">Running{{ $jobStatus['updated_at'] ? ' · '.$jobStatus['updated_at'] : '' }}</flux:badge>
                @elseif ($jobStatus['status'] === 'completed')
                    <div class="flex items-center gap-1">
                        <flux:badge color="green" icon="check-circle">Completed{{ $jobStatus['updated_at'] ? ' · '.$jobStatus['updated_at'] : '' }}</flux:badge>
                        <button wire:click="dismissJobStatus" type="button" class="p-0.5 rounded text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="Dismiss">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                @elseif ($jobStatus['status'] === 'failed')
                    <div class="flex items-center gap-1">
                        <flux:badge color="red" icon="exclamation-triangle">Failed{{ $jobStatus['updated_at'] ? ' · '.$jobStatus['updated_at'] : '' }}</flux:badge>
                        <button wire:click="dismissJobStatus" type="button" class="p-0.5 rounded text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-600 hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="Dismiss">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                @endif
            </div>
            <flux:button variant="primary" icon="plus" wire:click="createBackup">
                Create Backup Now
            </flux:button>
        </div>

        @if (count($this->localBackups) === 0)
            <p class="text-sm text-zinc-500 dark:text-zinc-400">No local backups found.</p>
            @if ($jobStatus['status'] === 'completed' && $jobStatus['filename'])
                @if (! $jobStatus['scan_dir_exists'])
                    <div class="mt-3 rounded border border-yellow-300 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-700 p-3 text-sm space-y-2">
                        <p class="font-medium text-yellow-800 dark:text-yellow-300">Local backup storage is not shared between the queue worker and web server.</p>
                        <p class="text-yellow-700 dark:text-yellow-400">
                            The worker successfully created <code class="font-mono">{{ $jobStatus['filename'] }}</code>, but the web process cannot see the
                            <code class="font-mono">{{ $jobStatus['scan_dir'] }}</code> directory at all. This happens when the worker and web containers
                            have separate filesystems (e.g. Docker containers without a shared storage volume).
                        </p>
                        <p class="text-yellow-700 dark:text-yellow-400">
                            <strong>Fix:</strong> Mount the same volume for <code class="font-mono">storage/app</code> in both your web and worker containers.
                            Until then, use <strong>S3 Backups</strong> — they are shared across all processes and already scheduled automatically.
                        </p>
                    </div>
                @else
                    <div class="mt-3 rounded border border-yellow-300 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-700 p-3 text-sm space-y-1">
                        <p class="font-medium text-yellow-800 dark:text-yellow-300">Backup completed but file is not visible here.</p>
                        <p class="text-yellow-700 dark:text-yellow-400">Worker path: <code class="font-mono">{{ $jobStatus['full_path'] ?? 'unknown' }}</code></p>
                        <p class="text-yellow-700 dark:text-yellow-400">Files in scan dir: {{ $jobStatus['scan_dir_contents'] }}</p>
                        @if ($jobStatus['open_basedir'])
                            <p class="text-yellow-700 dark:text-yellow-400">open_basedir restriction: <code class="font-mono">{{ $jobStatus['open_basedir'] }}</code></p>
                        @endif
                    </div>
                @endif
            @endif
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Filename</flux:table.column>
                    <flux:table.column>DB Type</flux:table.column>
                    <flux:table.column>Size</flux:table.column>
                    <flux:table.column>Created</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->localBackups as $backup)
                        <flux:table.row wire:key="backup-{{ $backup['filename'] }}">
                            <flux:table.cell class="font-mono text-sm">{{ $backup['filename'] }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" color="blue">{{ $backup['db_type'] }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $backup['size'] }}</flux:table.cell>
                            <flux:table.cell>{{ $backup['date'] }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex gap-2">
                                    <flux:button size="sm" icon="arrow-down-tray" wire:click="download('{{ $backup['filename'] }}')">
                                        Download
                                    </flux:button>
                                    <flux:button size="sm" variant="primary" icon="arrow-path" wire:click="confirmRestore('{{ $backup['filename'] }}')">
                                        Restore
                                    </flux:button>
                                    <flux:button size="sm" variant="danger" icon="trash" wire:click="confirmDelete('{{ $backup['filename'] }}')">
                                        Delete
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    {{-- S3 Backups Panel --}}
    <flux:card class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">S3 Backups</flux:heading>
            @if ($this->s3Configured)
                @if ($this->s3Connected)
                    <flux:badge color="green" icon="check-circle">S3 Connected</flux:badge>
                @else
                    <flux:badge color="red" icon="exclamation-triangle">S3 Unreachable</flux:badge>
                @endif
            @else
                <flux:badge color="zinc" icon="exclamation-circle">S3 Not Configured</flux:badge>
            @endif
        </div>

        @if (! $this->s3Configured)
            <p class="text-sm text-zinc-500 dark:text-zinc-400">S3 is not configured. Set <code>AWS_ACCESS_KEY_ID</code> and <code>AWS_S3_BUCKET</code> to enable S3 backups.</p>
        @elseif (! $this->s3Connected)
            <p class="text-sm text-red-500">Unable to connect to S3. Check credentials and bucket configuration.</p>
        @elseif (count($this->s3Backups) === 0)
            <p class="text-sm text-zinc-500 dark:text-zinc-400">No S3 backups found.</p>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Filename</flux:table.column>
                    <flux:table.column>Size</flux:table.column>
                    <flux:table.column>Stored</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->s3Backups as $backup)
                        <flux:table.row wire:key="s3-{{ $backup['filename'] }}">
                            <flux:table.cell class="font-mono text-sm">{{ $backup['filename'] }}</flux:table.cell>
                            <flux:table.cell>{{ $backup['size'] }}</flux:table.cell>
                            <flux:table.cell>{{ $backup['date'] }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex gap-2">
                                    <flux:button size="sm" icon="arrow-down-tray" wire:click="downloadFromS3('{{ $backup['filename'] }}')">
                                        Download
                                    </flux:button>
                                    <flux:button size="sm" variant="danger" icon="trash" wire:click="confirmDeleteS3('{{ $backup['filename'] }}')">
                                        Delete
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    {{-- Upload Panel --}}
    <flux:card class="mb-6">
        <flux:heading size="lg" class="mb-4">Upload Backup</flux:heading>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">
            Upload a <code>.sql.gz</code> backup file from another server.
            For files larger than the PHP upload limit, use SCP to place the file directly
            in <code>storage/app/backups/</code>.
        </p>
        <form wire:submit="uploadBackup" class="flex items-end gap-3">
            <div class="flex-1">
                <flux:input type="file" wire:model="uploadFile" accept=".gz" />
                @error('uploadFile')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <flux:button type="submit" variant="primary" icon="arrow-up-tray">
                Upload
            </flux:button>
        </form>
    </flux:card>

    {{-- Storage Stats Panel --}}
    <flux:card class="mb-6">
        <flux:heading size="lg" class="mb-4">File Asset Storage</flux:heading>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">
            File counts and sizes per directory — local server vs. S3. Use this to confirm all assets have been migrated.
        </p>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Directory</flux:table.column>
                <flux:table.column>Path</flux:table.column>
                <flux:table.column>Local Files</flux:table.column>
                <flux:table.column>Local Size</flux:table.column>
                @if ($this->s3Connected)
                    <flux:table.column>S3 Files</flux:table.column>
                    <flux:table.column>S3 Size</flux:table.column>
                @endif
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->storageStats as $stat)
                    <flux:table.row wire:key="stat-{{ $stat['directory'] }}">
                        <flux:table.cell>{{ $stat['label'] }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm text-zinc-500">{{ $stat['directory'] }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($stat['local_count'] === 0)
                                <flux:badge color="green" size="sm">0</flux:badge>
                            @else
                                <flux:badge color="yellow" size="sm">{{ number_format($stat['local_count']) }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $stat['local_size'] }}</flux:table.cell>
                        @if ($this->s3Connected)
                            <flux:table.cell>
                                @if ($stat['s3_count'] > 0)
                                    <flux:badge color="green" size="sm">{{ number_format($stat['s3_count']) }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">0</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $stat['s3_size'] }}</flux:table.cell>
                        @endif
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        @if (! $this->s3Connected)
            <p class="mt-3 text-sm text-zinc-400 dark:text-zinc-500">S3 not connected — configure AWS credentials to see S3 file counts.</p>
        @endif
    </flux:card>

    {{-- Settings Panel --}}
    <flux:card>
        <flux:heading size="lg" class="mb-4">Settings</flux:heading>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-medium">Take site offline during backup</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Puts the site in maintenance mode while a backup is running.</p>
                </div>
                <flux:switch wire:model.live="offlineDuringBackup" />
            </div>
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-medium">Take site offline during restore</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Puts the site in maintenance mode while a restore is running.</p>
                </div>
                <flux:switch wire:model.live="offlineDuringRestore" />
            </div>
        </div>
    </flux:card>

    {{-- Restore Confirmation Modal --}}
    <flux:modal name="confirm-restore" class="w-full max-w-md">
        <flux:heading size="lg">Restore Database</flux:heading>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            Are you sure you want to restore from
            <span class="font-mono font-semibold">{{ $restoreTarget }}</span>?
            This will replace the current database contents.
        </p>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button x-on:click="$flux.modal('confirm-restore').close()">Cancel</flux:button>
            <flux:button variant="danger" wire:click="restoreBackup">Restore</flux:button>
        </div>
    </flux:modal>

    {{-- Delete Confirmation Modal --}}
    <flux:modal name="confirm-delete" class="w-full max-w-md">
        <flux:heading size="lg">Delete Backup</flux:heading>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            Are you sure you want to permanently delete
            <span class="font-mono font-semibold">{{ $deleteTarget }}</span>?
            This cannot be undone.
        </p>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button x-on:click="$flux.modal('confirm-delete').close()">Cancel</flux:button>
            <flux:button variant="danger" wire:click="deleteBackup">Delete</flux:button>
        </div>
    </flux:modal>

    {{-- S3 Delete Confirmation Modal --}}
    <flux:modal name="confirm-delete-s3" class="w-full max-w-md">
        <flux:heading size="lg">Delete S3 Backup</flux:heading>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            Are you sure you want to permanently delete
            <span class="font-mono font-semibold">{{ $deleteS3Target }}</span>
            from S3? This cannot be undone.
        </p>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button x-on:click="$flux.modal('confirm-delete-s3').close()">Cancel</flux:button>
            <flux:button variant="danger" wire:click="deleteS3Backup">Delete</flux:button>
        </div>
    </flux:modal>
</div>
