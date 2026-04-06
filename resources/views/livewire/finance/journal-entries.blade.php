<?php

use App\Actions\CreateReversingEntry;
use App\Models\FinancialAccount;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialTag;
use App\Models\FinancialVendor;
use Flux\Flux;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // Filters
    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public string $filterType = '';

    public string $filterAccountId = '';

    public string $filterVendorId = '';

    public string $filterTagId = '';

    public function mount(): void
    {
        $this->authorize('finance-view');
    }

    public function updatedFilterDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterAccountId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterVendorId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterTagId(): void
    {
        $this->resetPage();
    }

    public function getEntriesProperty()
    {
        $query = FinancialJournalEntry::with(['vendor', 'lines.account', 'tags', 'period', 'reversesEntry', 'reversedBy'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($this->filterDateFrom) {
            $query->where('date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo) {
            $query->where('date', '<=', $this->filterDateTo);
        }

        if ($this->filterType) {
            $query->where('entry_type', $this->filterType);
        }

        if ($this->filterVendorId) {
            $query->where('vendor_id', (int) $this->filterVendorId);
        }

        if ($this->filterAccountId) {
            $query->whereHas('lines', fn ($q) => $q->where('account_id', (int) $this->filterAccountId));
        }

        if ($this->filterTagId) {
            $query->whereHas('tags', fn ($q) => $q->where('financial_tags.id', (int) $this->filterTagId));
        }

        return $query->paginate(25);
    }

    public function getAccountsProperty()
    {
        return FinancialAccount::where('is_active', true)->orderBy('code')->get();
    }

    public function getVendorsProperty()
    {
        return FinancialVendor::where('is_active', true)->orderBy('name')->get();
    }

    public function getTagsProperty()
    {
        return FinancialTag::orderBy('name')->get();
    }

    public function clearFilters(): void
    {
        $this->filterDateFrom = '';
        $this->filterDateTo = '';
        $this->filterType = '';
        $this->filterAccountId = '';
        $this->filterVendorId = '';
        $this->filterTagId = '';
        $this->resetPage();
    }

    public function reverse(int $entryId): void
    {
        $this->authorize('finance-record');

        $entry = FinancialJournalEntry::with('lines')->findOrFail($entryId);

        try {
            CreateReversingEntry::run(auth()->user(), $entry);
            Flux::toast('Reversing entry created as draft.', 'Done', variant: 'success');
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');
        }
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Journal Entries</flux:heading>
            <flux:text variant="subtle">All income, expense, and transfer transactions.</flux:text>
        </div>
        @can('finance-record')
            <flux:button variant="primary" href="{{ route('finance.journal.create') }}" wire:navigate>
                New Entry
            </flux:button>
        @endcan
    </div>

    {{-- Filters --}}
    <flux:card>
        <flux:heading size="sm" class="mb-3">Filters</flux:heading>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <flux:field>
                <flux:label>From Date</flux:label>
                <flux:input type="date" wire:model.live="filterDateFrom" />
            </flux:field>

            <flux:field>
                <flux:label>To Date</flux:label>
                <flux:input type="date" wire:model.live="filterDateTo" />
            </flux:field>

            <flux:field>
                <flux:label>Type</flux:label>
                <flux:select wire:model.live="filterType">
                    <flux:select.option value="">All types</flux:select.option>
                    <flux:select.option value="income">Income</flux:select.option>
                    <flux:select.option value="expense">Expense</flux:select.option>
                    <flux:select.option value="transfer">Transfer</flux:select.option>
                    <flux:select.option value="journal">Journal</flux:select.option>
                    <flux:select.option value="closing">Closing</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Account</flux:label>
                <flux:select wire:model.live="filterAccountId">
                    <flux:select.option value="">All accounts</flux:select.option>
                    @foreach ($this->accounts as $account)
                        <flux:select.option value="{{ $account->id }}">
                            {{ $account->code }} — {{ $account->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Vendor</flux:label>
                <flux:select wire:model.live="filterVendorId">
                    <flux:select.option value="">All vendors</flux:select.option>
                    @foreach ($this->vendors as $vendor)
                        <flux:select.option value="{{ $vendor->id }}">{{ $vendor->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Tag</flux:label>
                <flux:select wire:model.live="filterTagId">
                    <flux:select.option value="">All tags</flux:select.option>
                    @foreach ($this->tags as $tag)
                        <flux:select.option value="{{ $tag->id }}">{{ $tag->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>

        @if ($filterDateFrom || $filterDateTo || $filterType || $filterAccountId || $filterVendorId || $filterTagId)
            <div class="mt-3">
                <flux:button variant="ghost" size="sm" wire:click="clearFilters">Clear Filters</flux:button>
            </div>
        @endif
    </flux:card>

    {{-- Entries table --}}
    <flux:card>
        @if ($this->entries->isEmpty())
            <p class="text-sm text-zinc-500 dark:text-zinc-400 py-8 text-center">No journal entries found.</p>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Description</flux:table.column>
                    <flux:table.column>Type</flux:table.column>
                    <flux:table.column>Amount</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Lines</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->entries as $entry)
                        <flux:table.row wire:key="entry-{{ $entry->id }}">
                            <flux:table.cell>
                                <span class="text-sm">{{ $entry->date->format('M j, Y') }}</span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div>
                                    <span class="text-sm font-medium">{{ $entry->description }}</span>
                                    @if ($entry->vendor)
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $entry->vendor->name }}</div>
                                    @endif
                                    @if ($entry->tags->isNotEmpty())
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach ($entry->tags as $tag)
                                                <flux:badge color="{{ $tag->color }}" size="sm">{{ $tag->name }}</flux:badge>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge
                                    color="{{ match($entry->entry_type) {
                                        'income'   => 'green',
                                        'expense'  => 'red',
                                        'transfer' => 'blue',
                                        'closing'  => 'purple',
                                        default    => 'zinc',
                                    } }}"
                                    size="sm"
                                >
                                    {{ ucfirst($entry->entry_type) }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell class="font-mono text-sm">
                                ${{ number_format($entry->lines->sum('debit') / 100, 2) }}
                            </flux:table.cell>

                            <flux:table.cell>
                                @if ($entry->status === 'posted')
                                    <flux:badge color="green" size="sm">Posted</flux:badge>
                                @else
                                    <flux:badge color="yellow" size="sm">Draft</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="text-xs space-y-0.5">
                                    @foreach ($entry->lines as $line)
                                        <div class="flex gap-2">
                                            @if ($line->debit > 0)
                                                <span class="text-blue-600 dark:text-blue-400 w-12">Dr</span>
                                            @else
                                                <span class="text-green-600 dark:text-green-400 w-12">Cr</span>
                                            @endif
                                            <span class="text-zinc-600 dark:text-zinc-400">{{ $line->account?->name }}</span>
                                            <span class="font-mono">${{ number_format(($line->debit ?: $line->credit) / 100, 2) }}</span>
                                        </div>
                                    @endforeach
                                    @if ($entry->reversesEntry)
                                        <div class="text-zinc-400 mt-1">Reverses #{{ $entry->reversesEntry->id }}</div>
                                    @endif
                                    @if ($entry->reversedBy)
                                        <div class="text-zinc-400 mt-1">Reversed by #{{ $entry->reversedBy->id }}</div>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if ($entry->status === 'posted' && ! $entry->reversedBy)
                                    @can('finance-record')
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="reverse({{ $entry->id }})"
                                            wire:confirm="Create a reversing entry for this posted transaction?"
                                        >
                                            Reverse
                                        </flux:button>
                                    @endcan
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="mt-4">
                {{ $this->entries->links() }}
            </div>
        @endif
    </flux:card>
</div>
