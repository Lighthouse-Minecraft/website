<?php

use App\Models\FinancialTag;
use Livewire\Volt\Component;

/**
 * Tag search-or-create modal (supports multi-select).
 *
 * Usage in a parent Volt component:
 *
 *   @livewire('finance.tag-search-modal')
 *
 * The parent should listen for events:
 *   #[On('tag-selected')]
 *   public function onTagSelected(int $tagId, string $tagName, string $tagColor): void { ... }
 *
 * Trigger opening with:
 *   $dispatch('open-tag-search')
 *
 * Pass already-selected tag IDs to exclude them from the list:
 *   $dispatch('open-tag-search', selectedTagIds: [1, 2])
 */
new class extends Component
{
    public bool $open = false;

    public string $search = '';

    public array $selectedTagIds = [];

    public function getResultsProperty(): array
    {
        $query = FinancialTag::whereNotIn('id', $this->selectedTagIds);

        if (strlen($this->search) >= 1) {
            $query->where('name', 'like', '%'.$this->search.'%');
        }

        return $query->orderBy('name')->limit(10)->get(['id', 'name', 'color'])->toArray();
    }

    public function getShowCreateOptionProperty(): bool
    {
        if (strlen(trim($this->search)) === 0) {
            return false;
        }

        $exactMatch = collect($this->results)->first(
            fn ($t) => strtolower($t['name']) === strtolower(trim($this->search))
        );

        return $exactMatch === null;
    }

    public function close(): void
    {
        $this->open = false;
        $this->search = '';
        $this->selectedTagIds = [];
    }

    public function select(int $id, string $name, string $color): void
    {
        $this->dispatch('tag-selected', tagId: $id, tagName: $name, tagColor: $color);
        $this->close();
    }

    public function createAndSelect(): void
    {
        $name = trim($this->search);

        if (! $name) {
            return;
        }

        $tag = FinancialTag::create(['name' => $name, 'color' => 'zinc']);

        $this->dispatch('tag-selected', tagId: $tag->id, tagName: $tag->name, tagColor: $tag->color);
        $this->close();
    }

    #[\Livewire\Attributes\On('open-tag-search')]
    public function handleOpenEvent(array $selectedTagIds = []): void
    {
        $this->selectedTagIds = $selectedTagIds;
        $this->open = true;
        $this->search = '';
    }
}; ?>

<div>
    @if ($open)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:click.self="close">
            <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-xl w-full max-w-md mx-4 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Add Tag</flux:heading>
                    <flux:button variant="ghost" icon="x-mark" size="sm" wire:click="close" />
                </div>

                <flux:input
                    wire:model.live="search"
                    placeholder="Search tags..."
                    icon="magnifying-glass"
                />

                <div class="space-y-1 max-h-64 overflow-y-auto">
                    @foreach ($this->results as $tag)
                        <button
                            wire:click="select({{ $tag['id'] }}, '{{ addslashes($tag['name']) }}', '{{ $tag['color'] }}')"
                            class="w-full text-left px-3 py-2 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 text-sm transition-colors flex items-center gap-2"
                        >
                            <flux:badge color="{{ $tag['color'] }}" size="sm">{{ $tag['name'] }}</flux:badge>
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
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 py-4 text-center">No tags found.</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
