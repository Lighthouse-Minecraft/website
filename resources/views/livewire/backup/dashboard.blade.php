<?php

use App\Jobs\CreateBackupJob;
use Flux\Flux;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    public ?string $deleteTarget = null;

    public function mount(): void
    {
        $this->authorize('backup-manager');
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
            $dbType = $m[1] ?? 'unknown';

            return [
                'filename' => $filename,
                'size'     => $this->formatBytes(filesize($path)),
                'date'     => \Carbon\Carbon::createFromTimestamp(filemtime($path))->format('Y-m-d H:i'),
                'db_type'  => $dbType,
            ];
        }, $files);
    }

    public function createBackup(): void
    {
        $this->authorize('backup-manager');
        CreateBackupJob::dispatch();
        Flux::toast('Backup job queued.', 'Success', variant: 'success');
    }

    public function download(string $filename): StreamedResponse
    {
        $this->authorize('backup-manager');

        $path = storage_path("app/backups/{$filename}");

        abort_if(! file_exists($path), 404);

        return response()->streamDownload(function () use ($path) {
            readfile($path);
        }, $filename, ['Content-Type' => 'application/gzip']);
    }

    public function confirmDelete(string $filename): void
    {
        $this->deleteTarget = $filename;
        Flux::modal('confirm-delete')->show();
    }

    public function deleteBackup(): void
    {
        $this->authorize('backup-manager');

        if ($this->deleteTarget === null) {
            return;
        }

        $path = storage_path("app/backups/{$this->deleteTarget}");

        if (file_exists($path)) {
            unlink($path);
        }

        $this->deleteTarget = null;
        Flux::modal('confirm-delete')->close();
        Flux::toast('Backup deleted.', 'Done', variant: 'success');
        unset($this->localBackups);
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

<x-layouts.app>
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
</x-layouts.app>
