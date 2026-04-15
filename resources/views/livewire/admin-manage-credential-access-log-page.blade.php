<?php

use App\Models\CredentialAccessLog;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $filterAction = '';

    public array $distinctActions = [];

    public function mount(): void
    {
        $this->authorize('view-credential-access-log');

        $this->distinctActions = CredentialAccessLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->toArray();
    }

    public function updatedFilterAction(): void
    {
        $this->resetPage();
    }

    #[\Livewire\Attributes\Computed]
    public function logs()
    {
        return CredentialAccessLog::query()
            ->with(['credential', 'user'])
            ->when($this->filterAction, fn ($q) => $q->where('action', $this->filterAction))
            ->orderByDesc('created_at')
            ->paginate(25);
    }
}; ?>

<div class="w-full max-w-full space-y-6">
    <div class="flex items-center gap-4">
        <flux:heading size="xl">Credential Access Log</flux:heading>
        <flux:spacer />
        <flux:select wire:model.live="filterAction" size="sm" class="w-56">
            <flux:select.option value="">All Actions</flux:select.option>
            @foreach ($distinctActions as $action)
                <flux:select.option value="{{ $action }}">{{ Str::of($action)->replace('_', ' ')->title() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->logs">
        <flux:table.columns>
            <flux:table.column>Date / Time</flux:table.column>
            <flux:table.column>User</flux:table.column>
            <flux:table.column>Credential</flux:table.column>
            <flux:table.column>Action</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->logs as $log)
                <flux:table.row wire:key="log-{{ $log->id }}">
                    <flux:table.cell class="text-sm text-zinc-500">
                        {{ $log->created_at->format('M j, Y g:i A') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $log->user?->name ?? '(deleted user)' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $log->credential?->name ?? '(deleted credential)' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="zinc" size="sm">
                            {{ Str::of($log->action)->replace('_', ' ')->title() }}
                        </flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
