<?php

use App\Models\MinecraftAccount;
use App\Models\MinecraftCommandLog;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $typeFilter = '';
    public ?MinecraftAccount $selectedAccount = null;

    /**
 * Reset the pagination page when the search filter changes.
 */
public function updatedSearch(): void { $this->resetPage(); }
    /**
 * Reset the current pagination page to the first page when the status filter is updated.
 */
public function updatedStatusFilter(): void { $this->resetPage(); }
    /**
 * Reset the pagination page when the command type filter changes.
 *
 * Ensures the log listing returns to the first page after updating the type filter.
 */
public function updatedTypeFilter(): void { $this->resetPage(); }

    /**
     * Selects a Minecraft account by command ID or username and opens the account detail modal if found.
     *
     * Performs an authorization check before loading the account. If a matching account is found,
     * assigns it to $this->selectedAccount and shows the "mc-account-detail" modal.
     *
     * @param string $target Command ID or username to look up.
     */
    public function showAccount(string $target): void
    {
        $this->authorize('viewAny', MinecraftAccount::class);

        $this->selectedAccount = MinecraftAccount::with('user')
            ->where('username', $target)
            ->first();

        if ($this->selectedAccount) {
            $this->modal('mc-account-detail')->show();
        }
    }

    /**
     * Retrieve paginated Minecraft command logs filtered by the component's search, status, and type filters.
     *
     * The result is ordered by `executed_at` (latest first) and includes the related `user` for each log.
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\MinecraftCommandLog> Paginated collection of MinecraftCommandLog models.
     */
    #[\Livewire\Attributes\Computed]
    public function logs()
    {
        return MinecraftCommandLog::query()
            ->with('user')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('command', 'like', "%{$this->search}%")
                    ->orWhere('target', 'like', "%{$this->search}%");
            }))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter, fn ($q) => $q->where('command_type', $this->typeFilter))
            ->latest('executed_at')
            ->paginate(25);
    }

    /**
     * Retrieve the distinct command types from the command log in ascending order.
     *
     * @return string[] An array of distinct `command_type` values ordered by `command_type`.
     */
    #[\Livewire\Attributes\Computed]
    public function commandTypes(): array
    {
        return MinecraftCommandLog::query()
            ->distinct()
            ->orderBy('command_type')
            ->pluck('command_type')
            ->toArray();
    }
}; ?>

<div class="space-y-4">
    <flux:heading size="xl">MC Command Log</flux:heading>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-3">
        <flux:input
            wire:model.live.debounce.400ms="search"
            placeholder="Search command or target..."
            icon="magnifying-glass"
            size="sm"
            class="w-64" />

        <flux:select wire:model.live="statusFilter" size="sm" placeholder="All statuses" class="w-40">
            <flux:select.option value="">All statuses</flux:select.option>
            <flux:select.option value="success">Success</flux:select.option>
            <flux:select.option value="failed">Failed</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="typeFilter" size="sm" placeholder="All types" class="w-40">
            <flux:select.option value="">All types</flux:select.option>
            @foreach($this->commandTypes as $type)
                <flux:select.option value="{{ $type }}">{{ Str::title($type) }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Table --}}
    <flux:table :paginate="$this->logs">
        <flux:table.columns>
            <flux:table.column>Date / Time</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Command</flux:table.column>
            <flux:table.column>Target</flux:table.column>
            <flux:table.column>Triggered By</flux:table.column>
            <flux:table.column>Status</flux:table.column>
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
                        <flux:badge size="sm" color="{{ match($log->command_type) { 'whitelist' => 'blue', 'rank' => 'purple', 'verify' => 'green', default => 'zinc' } }}">
                            {{ $log->command_type }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell
                        class="font-mono text-xs max-w-xs truncate"
                        title="{{ $log->command }}">
                        {{ $log->command }}
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap text-sm">
                        @if($log->target)
                            <button wire:click="showAccount('{{ $log->target }}')"
                                    class="text-blue-600 dark:text-blue-400 hover:underline font-mono text-xs cursor-pointer">
                                {{ $log->target }}
                            </button>
                        @else
                            —
                        @endif
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

    <x-minecraft.mc-account-detail-modal :account="$selectedAccount" />
</div>