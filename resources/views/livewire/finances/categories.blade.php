<?php

use App\Actions\ArchiveFinancialCategory;
use App\Actions\ArchiveFinancialTag;
use App\Actions\CreateFinancialCategory;
use App\Actions\CreateFinancialTag;
use App\Actions\ReorderFinancialCategory;
use App\Actions\UpdateFinancialCategory;
use App\Models\FinancialCategory;
use App\Models\FinancialTag;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    // Category create
    public string $newCatName = '';
    public string $newCatType = 'expense';
    public string $newCatParentId = '';

    // Category edit
    public ?int $editCatId = null;
    public string $editCatName = '';

    // Category reorder
    public ?int $reorderCatId = null;
    public int $reorderSortOrder = 0;

    // Tag create
    public string $newTagName = '';

    public function topLevelCategories(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialCategory::whereNull('parent_id')
            ->orderBy('type')
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->get();
    }

    public function tags(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialTag::orderBy('name')->get();
    }

    public function createCategory(): void
    {
        $this->authorize('financials-manage');

        $this->validate([
            'newCatName' => 'required|string|max:255',
            'newCatType' => 'required|in:income,expense',
            'newCatParentId' => 'nullable|integer|exists:financial_categories,id',
        ]);

        $parentId = $this->newCatParentId !== '' ? (int) $this->newCatParentId : null;

        CreateFinancialCategory::run($this->newCatName, $this->newCatType, $parentId);

        Flux::modal('create-category-modal')->close();
        Flux::toast('Category created.', 'Success', variant: 'success');
        $this->reset(['newCatName', 'newCatType', 'newCatParentId']);
    }

    public function openEditCategoryModal(int $id): void
    {
        $this->authorize('financials-manage');

        $category = FinancialCategory::findOrFail($id);
        $this->editCatId = $id;
        $this->editCatName = $category->name;

        Flux::modal('edit-category-modal')->show();
    }

    public function updateCategory(): void
    {
        $this->authorize('financials-manage');

        $this->validate([
            'editCatName' => 'required|string|max:255',
        ]);

        $category = FinancialCategory::findOrFail($this->editCatId);
        UpdateFinancialCategory::run($category, $this->editCatName);

        Flux::modal('edit-category-modal')->close();
        Flux::toast('Category renamed.', 'Success', variant: 'success');
        $this->reset(['editCatId', 'editCatName']);
    }

    public function archiveCategory(int $id): void
    {
        $this->authorize('financials-manage');

        $category = FinancialCategory::findOrFail($id);
        ArchiveFinancialCategory::run($category);

        Flux::toast('Category archived.', 'Success', variant: 'success');
    }

    public function openReorderModal(int $id): void
    {
        $this->authorize('financials-manage');

        $category = FinancialCategory::findOrFail($id);
        $this->reorderCatId = $id;
        $this->reorderSortOrder = $category->sort_order;

        Flux::modal('reorder-category-modal')->show();
    }

    public function reorderCategory(): void
    {
        $this->authorize('financials-manage');

        $this->validate([
            'reorderSortOrder' => 'required|integer|min:0',
        ]);

        $category = FinancialCategory::findOrFail($this->reorderCatId);
        ReorderFinancialCategory::run($category, $this->reorderSortOrder);

        Flux::modal('reorder-category-modal')->close();
        Flux::toast('Category reordered.', 'Success', variant: 'success');
        $this->reset(['reorderCatId', 'reorderSortOrder']);
    }

    public function createTag(): void
    {
        $this->authorize('financials-manage');

        $this->validate([
            'newTagName' => 'required|string|max:255',
        ]);

        CreateFinancialTag::run($this->newTagName, auth()->user());

        Flux::modal('create-tag-modal')->close();
        Flux::toast('Tag created.', 'Success', variant: 'success');
        $this->reset(['newTagName']);
    }

    public function archiveTag(int $id): void
    {
        $this->authorize('financials-manage');

        $tag = FinancialTag::findOrFail($id);
        ArchiveFinancialTag::run($tag);

        Flux::toast('Tag archived.', 'Success', variant: 'success');
    }
}; ?>

<div class="space-y-8">

    {{-- Finance Navigation --}}
    <div class="flex flex-wrap gap-2">
        <flux:button href="{{ route('finances.dashboard') }}" wire:navigate size="sm" icon="banknotes">Dashboard</flux:button>
        <flux:button href="{{ route('finances.budget') }}" wire:navigate size="sm" icon="calculator">Budget</flux:button>
        <flux:button href="{{ route('finances.reports') }}" wire:navigate size="sm" icon="document-text">Period Reports</flux:button>
        @can('financials-manage')
            <flux:button href="{{ route('finances.board-reports') }}" wire:navigate size="sm" icon="chart-bar">Board Reports</flux:button>
            <flux:button href="{{ route('finances.accounts') }}" wire:navigate size="sm" icon="building-library">Accounts</flux:button>
            <flux:button href="{{ route('finances.categories') }}" wire:navigate size="sm" icon="tag">Categories</flux:button>
        @endcan
    </div>

    {{-- ===== CATEGORIES ===== --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">Financial Categories</flux:heading>
            @can('financials-manage')
                <flux:modal.trigger name="create-category-modal">
                    <flux:button variant="primary" icon="plus">New Category</flux:button>
                </flux:modal.trigger>
            @endcan
        </div>

        @foreach (['expense' => 'Expense Categories', 'income' => 'Income Categories'] as $type => $label)
            <div class="space-y-2">
                <flux:heading size="md">{{ $label }}</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>Sort</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        @can('financials-manage')
                            <flux:table.column>Actions</flux:table.column>
                        @endcan
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->topLevelCategories()->where('type', $type) as $category)
                            <flux:table.row wire:key="cat-{{ $category->id }}">
                                <flux:table.cell class="font-medium">{{ $category->name }}</flux:table.cell>
                                <flux:table.cell>{{ $category->sort_order }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($category->is_archived)
                                        <flux:badge variant="warning">Archived</flux:badge>
                                    @else
                                        <flux:badge variant="success">Active</flux:badge>
                                    @endif
                                </flux:table.cell>
                                @can('financials-manage')
                                    <flux:table.cell>
                                        <div class="flex gap-2">
                                            <flux:button size="sm" icon="pencil-square" wire:click="openEditCategoryModal({{ $category->id }})">Rename</flux:button>
                                            <flux:button size="sm" icon="arrows-up-down" wire:click="openReorderModal({{ $category->id }})">Reorder</flux:button>
                                            @unless ($category->is_archived)
                                                <flux:button size="sm" variant="danger" icon="archive-box"
                                                    wire:click="archiveCategory({{ $category->id }})"
                                                    wire:confirm="Archive this category? It will be hidden from entry forms.">
                                                    Archive
                                                </flux:button>
                                            @endunless
                                        </div>
                                    </flux:table.cell>
                                @endcan
                            </flux:table.row>

                            {{-- Subcategories --}}
                            @foreach ($category->children->sortBy('sort_order') as $sub)
                                <flux:table.row wire:key="cat-{{ $sub->id }}">
                                    <flux:table.cell class="pl-8 text-zinc-500">↳ {{ $sub->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $sub->sort_order }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if ($sub->is_archived)
                                            <flux:badge variant="warning">Archived</flux:badge>
                                        @else
                                            <flux:badge variant="success">Active</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    @can('financials-manage')
                                        <flux:table.cell>
                                            <div class="flex gap-2">
                                                <flux:button size="sm" icon="pencil-square" wire:click="openEditCategoryModal({{ $sub->id }})">Rename</flux:button>
                                                <flux:button size="sm" icon="arrows-up-down" wire:click="openReorderModal({{ $sub->id }})">Reorder</flux:button>
                                                @unless ($sub->is_archived)
                                                    <flux:button size="sm" variant="danger" icon="archive-box"
                                                        wire:click="archiveCategory({{ $sub->id }})"
                                                        wire:confirm="Archive this subcategory? It will be hidden from entry forms.">
                                                        Archive
                                                    </flux:button>
                                                @endunless
                                            </div>
                                        </flux:table.cell>
                                    @endcan
                                </flux:table.row>
                            @endforeach
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endforeach
    </div>

    {{-- ===== TAGS ===== --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">Tags</flux:heading>
            @can('financials-manage')
                <flux:modal.trigger name="create-tag-modal">
                    <flux:button variant="primary" icon="plus">New Tag</flux:button>
                </flux:modal.trigger>
            @endcan
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                @can('financials-manage')
                    <flux:table.column>Actions</flux:table.column>
                @endcan
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->tags() as $tag)
                    <flux:table.row wire:key="tag-{{ $tag->id }}">
                        <flux:table.cell>{{ $tag->name }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($tag->is_archived)
                                <flux:badge variant="warning">Archived</flux:badge>
                            @else
                                <flux:badge variant="success">Active</flux:badge>
                            @endif
                        </flux:table.cell>
                        @can('financials-manage')
                            <flux:table.cell>
                                @unless ($tag->is_archived)
                                    <flux:button size="sm" variant="danger" icon="archive-box"
                                        wire:click="archiveTag({{ $tag->id }})"
                                        wire:confirm="Archive this tag? It will be removed from future transaction entry.">
                                        Archive
                                    </flux:button>
                                @endunless
                            </flux:table.cell>
                        @endcan
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- ===== MODALS ===== --}}
    @can('financials-manage')
        {{-- Create Category Modal --}}
        <flux:modal name="create-category-modal" class="w-full max-w-md space-y-6">
            <flux:heading size="lg">New Category</flux:heading>
            <form wire:submit.prevent="createCategory" class="space-y-4">
                <flux:field>
                    <flux:label>Name <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="newCatName" placeholder="e.g. Infrastructure" />
                    <flux:error name="newCatName" />
                </flux:field>

                <flux:field>
                    <flux:label>Type <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model.live="newCatType">
                        <flux:select.option value="expense">Expense</flux:select.option>
                        <flux:select.option value="income">Income</flux:select.option>
                    </flux:select>
                    <flux:error name="newCatType" />
                </flux:field>

                <flux:field>
                    <flux:label>Parent (leave blank for top-level)</flux:label>
                    <flux:select wire:model="newCatParentId">
                        <flux:select.option value="">— Top Level —</flux:select.option>
                        @foreach ($this->topLevelCategories()->where('type', $newCatType)->where('is_archived', false) as $parent)
                            <flux:select.option value="{{ $parent->id }}">{{ $parent->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="newCatParentId" />
                </flux:field>

                <div class="flex gap-3 pt-2">
                    <flux:button type="submit" variant="primary">Create</flux:button>
                    <flux:button x-on:click="$flux.modal('create-category-modal').close()" variant="ghost">Cancel</flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- Edit Category Modal --}}
        <flux:modal name="edit-category-modal" class="w-full max-w-md space-y-6">
            <flux:heading size="lg">Rename Category</flux:heading>
            <form wire:submit.prevent="updateCategory" class="space-y-4">
                <flux:field>
                    <flux:label>Name <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="editCatName" />
                    <flux:error name="editCatName" />
                </flux:field>
                <div class="flex gap-3 pt-2">
                    <flux:button type="submit" variant="primary">Save</flux:button>
                    <flux:button x-on:click="$flux.modal('edit-category-modal').close()" variant="ghost">Cancel</flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- Reorder Category Modal --}}
        <flux:modal name="reorder-category-modal" class="w-full max-w-sm space-y-6">
            <flux:heading size="lg">Set Sort Order</flux:heading>
            <form wire:submit.prevent="reorderCategory" class="space-y-4">
                <flux:field>
                    <flux:label>Sort Order <span class="text-red-500">*</span></flux:label>
                    <flux:description>Lower numbers appear first</flux:description>
                    <flux:input wire:model="reorderSortOrder" type="number" min="0" />
                    <flux:error name="reorderSortOrder" />
                </flux:field>
                <div class="flex gap-3 pt-2">
                    <flux:button type="submit" variant="primary">Save</flux:button>
                    <flux:button x-on:click="$flux.modal('reorder-category-modal').close()" variant="ghost">Cancel</flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- Create Tag Modal --}}
        <flux:modal name="create-tag-modal" class="w-full max-w-sm space-y-6">
            <flux:heading size="lg">New Tag</flux:heading>
            <form wire:submit.prevent="createTag" class="space-y-4">
                <flux:field>
                    <flux:label>Name <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="newTagName" placeholder="e.g. test-server" />
                    <flux:error name="newTagName" />
                </flux:field>
                <div class="flex gap-3 pt-2">
                    <flux:button type="submit" variant="primary">Create</flux:button>
                    <flux:button x-on:click="$flux.modal('create-tag-modal').close()" variant="ghost">Cancel</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</div>
