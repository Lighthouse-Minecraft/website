<?php

use App\Models\FinancialVendor;
use Livewire\Volt\Component;

/**
 * Vendor search-or-create modal.
 *
 * Usage in a parent Volt component:
 *
 *   @livewire('finance.vendor-search-modal')
 *
 * The parent should listen for the 'vendor-selected' event:
 *   #[On('vendor-selected')]
 *   public function onVendorSelected(int $vendorId, string $vendorName): void { ... }
 *
 * Trigger opening with:
 *   $dispatch('open-vendor-search')
 */
new class extends Component
{
    public bool $open = false;

    public string $search = '';

    public function getResultsProperty(): array
    {
        if (strlen($this->search) < 1) {
            return FinancialVendor::where('is_active', true)
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name'])
                ->toArray();
        }

        return FinancialVendor::where('is_active', true)
            ->where('name', 'like', '%'.$this->search.'%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name'])
            ->toArray();
    }

    public function getShowCreateOptionProperty(): bool
    {
        if (strlen(trim($this->search)) === 0) {
            return false;
        }

        // Show "Create" when no result has an exact case-insensitive name match
        $exactMatch = collect($this->results)->first(
            fn ($v) => strtolower($v['name']) === strtolower(trim($this->search))
        );

        return $exactMatch === null;
    }

    public function open(): void
    {
        $this->open = true;
        $this->search = '';
    }

    public function close(): void
    {
        $this->open = false;
        $this->search = '';
    }

    public function select(int $id, string $name): void
    {
        $this->dispatch('vendor-selected', vendorId: $id, vendorName: $name);
        $this->close();
    }

    public function createAndSelect(): void
    {
        $name = trim($this->search);

        if (! $name) {
            return;
        }

        $vendor = FinancialVendor::create(['name' => $name, 'is_active' => true]);

        $this->dispatch('vendor-selected', vendorId: $vendor->id, vendorName: $vendor->name);
        $this->close();
    }

    #[\Livewire\Attributes\On('open-vendor-search')]
    public function handleOpenEvent(): void
    {
        $this->open();
    }
}; ?>

<div>
    @if ($open)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:click.self="close">
            <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-xl w-full max-w-md mx-4 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Select Vendor</flux:heading>
                    <flux:button variant="ghost" icon="x-mark" size="sm" wire:click="close" />
                </div>

                <flux:input
                    wire:model.live="search"
                    placeholder="Search vendors..."
                    icon="magnifying-glass"
                />

                <div class="space-y-1 max-h-64 overflow-y-auto">
                    @foreach ($this->results as $vendor)
                        <button
                            wire:click="select({{ $vendor['id'] }}, '{{ addslashes($vendor['name']) }}')"
                            class="w-full text-left px-3 py-2 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 text-sm transition-colors"
                        >
                            {{ $vendor['name'] }}
                        </button>
                    @endforeach

                    @if ($this->showCreateOption)
                        <button
                            wire:click="createAndSelect"
                            class="w-full text-left px-3 py-2 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 text-sm text-blue-600 dark:text-blue-400 font-medium transition-colors border border-dashed border-blue-300 dark:border-blue-700 mt-2"
                        >
                            + Create "{{ $search }}"
                        </button>
                    @endif

                    @if (empty($this->results) && !$this->showCreateOption)
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 py-4 text-center">No vendors found.</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
