<?php

use App\Jobs\CreateBackupJob;
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

    public ?string $deleteTarget    = null;
    public ?string $restoreTarget   = null;
    public ?string $deleteS3Target  = null;

    // Settings
    public bool $offlineDuringBackup  = false;
    public bool $offlineDuringRestore = true;

    // Upload
    public $uploadFile = null;

    public function mount(): void
    {
        $this->authorize('backup-manager');
        $this->offlineDuringBackup  = SiteConfig::getValue('backup.offline_during_backup', 'false') === 'true';
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
        $dir   = storage_path('app/backups');
        $files = glob("{$dir}/*.sql.gz") ?: [];

        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

        return array_map(function ($path) {
            $filename = basename($path);
            preg_match('/_(pgsql|mysql|sqlite)\.sql\.gz$/', $filename, $m);

            return [
                'filename' => $filename,
                'size'     => $this->formatBytes(filesize($path)),
                'date'     => \Carbon\Carbon::createFromTimestamp(filemtime($path))->format('Y-m-d H:i'),
                'db_type'  => $m[1] ?? 'unknown',
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
        $publicDisk = config('filesystems.public_disk', 'public');

        $directories = [
            'Staff Photos'        => 'staff-photos',
            'Board Member Photos' => 'board-member-photos',
            'Community Stories'   => 'community-stories',
            'Message Images'      => 'message-images',
            'Report Evidence'     => 'report-evidence',
            'Blog Images'         => 'blog/images',
            'Blog Category Hero'  => 'blog/category-hero',
        ];

        $stats = [];

        foreach ($directories as $label => $dir) {
            $files     = Storage::disk($publicDisk)->allFiles($dir);
            $count     = count($files);
            $totalSize = 0;

            foreach ($files as $file) {
                $totalSize += Storage::disk($publicDisk)->size($file);
            }

            $stats[] = [
                'label'      => $label,
                'directory'  => $dir,
                'count'      => $count,
                'total_size' => $this->formatBytes($totalSize),
            ];
        }

        return $stats;
    }

    public function createBackup(): void
    {
        $this->authorize('backup-manager');
        CreateBackupJob::dispatch();
        Flux::toast('Backup job queued.', 'Success', variant: 'success');
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

        $this->uploadFile->storeAs('backups', $originalName, 'local');
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
        if (file_exists($path)) {
            unlink($path);
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

    {{-- Local Backups Panel --}}
    <flux:card class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">Local Backups</flux:heading>
            <flux:button variant="primary" icon="plus" wire:click="createBackup">
                Create Backup Now
            </flux:button>
        </div>

        @if (count($this->localBackups) === 0)
            <p class="text-sm text-zinc-500 dark:text-zinc-400">No local backups found.</p>
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
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">File counts and sizes for each public asset directory.</p>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Directory</flux:table.column>
                <flux:table.column>Path</flux:table.column>
                <flux:table.column>Files</flux:table.column>
                <flux:table.column>Total Size</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->storageStats as $stat)
                    <flux:table.row wire:key="stat-{{ $stat['directory'] }}">
                        <flux:table.cell>{{ $stat['label'] }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm text-zinc-500">{{ $stat['directory'] }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($stat['count']) }}</flux:table.cell>
                        <flux:table.cell>{{ $stat['total_size'] }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
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
