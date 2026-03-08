<?php

use App\Models\DiscordApiLog;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $typeFilter = '';

    public function mount(): void
    {
        $this->authorize('view-discord-api-log');
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedTypeFilter(): void { $this->resetPage(); }

    #[\Livewire\Attributes\Computed]
    public function logs()
    {
        return DiscordApiLog::query()
            ->with('user')
            ->when($this->search !== '', fn ($q) => $q->where(function ($q) {
                $q->where('endpoint', 'like', "%{$this->search}%")
                    ->orWhere('target', 'like', "%{$this->search}%")
                    ->orWhere('action_type', 'like', "%{$this->search}%");
            }))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('action_type', $this->typeFilter))
            ->latest('executed_at')
            ->paginate(25);
    }

    #[\Livewire\Attributes\Computed]
    public function actionTypes(): array
    {
        return DiscordApiLog::query()
            ->distinct()
            ->orderBy('action_type')
            ->pluck('action_type')
            ->toArray();
    }
}; ?>

<div class="space-y-4">
    <flux:heading size="xl">Discord API Log</flux:heading>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-3">
        <flux:input
            wire:model.live.debounce.400ms="search"
            placeholder="Search endpoint, target, or type..."
            icon="magnifying-glass"
            class="max-w-sm" />

        <flux:select wire:model.live="statusFilter" size="sm" placeholder="All statuses" class="w-40">
            <flux:select.option value="">All statuses</flux:select.option>
            <flux:select.option value="success">Success</flux:select.option>
            <flux:select.option value="failed">Failed</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="typeFilter" size="sm" placeholder="All types" class="w-40">
            <flux:select.option value="">All types</flux:select.option>
            @foreach($this->actionTypes as $type)
                <flux:select.option value="{{ $type }}">{{ Str::title(str_replace('_', ' ', $type)) }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Table --}}
    <flux:table :paginate="$this->logs">
        <flux:table.columns>
            <flux:table.column>Date / Time</flux:table.column>
            <flux:table.column>Action</flux:table.column>
            <flux:table.column>Method</flux:table.column>
            <flux:table.column>Endpoint</flux:table.column>
            <flux:table.column>Target</flux:table.column>
            <flux:table.column>Triggered By</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>HTTP</flux:table.column>
            <flux:table.column>Response / Error</flux:table.column>
            <flux:table.column>ms</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach($this->logs as $log)
                @php
                    $tz = auth()->user()->timezone ?? 'UTC';
                    $localTime = $log->executed_at->setTimezone($tz);
                @endphp
                <flux:table.row wire:key="log-{{ $log->id }}">
                    <flux:table.cell
                        class="whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400"
                        title="{{ $localTime->format('Y-m-d H:i:s T') }}">
                        {{ $localTime->format('M j, Y') }}<br>
                        {{ $localTime->format('g:i A') }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="{{ match($log->action_type) { 'add_role' => 'blue', 'remove_role' => 'purple', 'send_dm' => 'green', 'send_channel_message' => 'amber', 'get_member' => 'zinc', 'create_dm' => 'cyan', default => 'zinc' } }}">
                            {{ str_replace('_', ' ', $log->action_type) }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="font-mono text-xs">
                        {{ $log->method }}
                    </flux:table.cell>

                    <flux:table.cell
                        class="font-mono text-xs max-w-xs truncate"
                        title="{{ $log->endpoint }}">
                        {{ $log->endpoint }}
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap text-sm font-mono text-xs">
                        {{ $log->target ?? '—' }}
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">
                        @if($log->user)
                            <flux:link href="{{ route('profile.show', $log->user) }}">{{ $log->user->name }}</flux:link>
                        @else
                            <em class="text-zinc-400">System</em>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="{{ $log->status === 'success' ? 'green' : 'red' }}">
                            {{ $log->status }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="text-xs text-zinc-400 whitespace-nowrap">
                        {{ $log->http_status ?? '—' }}
                    </flux:table.cell>

                    <flux:table.cell
                        class="text-xs text-zinc-500 dark:text-zinc-400 max-w-xs truncate"
                        title="{{ $log->response ?? $log->error_message ?? '' }}">
                        {{ Str::limit($log->response ?? $log->error_message ?? '—', 60) }}
                    </flux:table.cell>

                    <flux:table.cell class="text-xs text-zinc-400 whitespace-nowrap">
                        {{ $log->execution_time_ms !== null ? $log->execution_time_ms.'ms' : '—' }}
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
