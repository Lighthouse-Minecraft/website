<?php

use App\Models\Category;
use App\Models\Tag;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new class extends Component {
    use WithPagination;

    public function getCategoriesProperty()
    {
        return Category::orderBy('name')
            ->paginate(10, ['*'], 'categoriesPage');
    }

    public function getTagsProperty()
    {
        return Tag::orderBy('name')
            ->paginate(10, ['*'], 'tagsPage');
    }

    // Create form state
    public string $newCategoryName = '';
    public string $newCategoryColor = '';
    public string $newTagName = '';
    public string $newTagColor = '';

    // Edit modal state
    public ?int $editingId = null;
    public string $editingType = ''; // 'category' | 'tag'
    public string $editingName = '';
    public string $editingColor = '';

    // Bulk selection state
    public array $selectedCategoryIds = [];
    public array $selectedTagIds = [];

    public function createCategory(): void
    {
        $this->authorize('create', Category::class);

        $this->validate([
            'newCategoryName' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'newCategoryColor' => ['nullable', 'string', 'max:50'],
        ]);

        Category::create([
            'name' => $this->newCategoryName,
            'slug' => Str::slug($this->newCategoryName),
            'color' => $this->newCategoryColor ?: null,
        ]);

        $this->reset(['newCategoryName', 'newCategoryColor']);
        $this->resetPage('categoriesPage');
        Flux::toast('Category created', 'Success', variant: 'success');
    }

    public function createTag(): void
    {
        $this->authorize('create', Tag::class);

        $this->validate([
            'newTagName' => ['required', 'string', 'max:255', 'unique:tags,name'],
            'newTagColor' => ['nullable', 'string', 'max:50'],
        ]);

        Tag::create([
            'name' => $this->newTagName,
            'slug' => Str::slug($this->newTagName),
            'color' => $this->newTagColor ?: null,
        ]);

        $this->reset(['newTagName', 'newTagColor']);
        $this->resetPage('tagsPage');
        Flux::toast('Tag created', 'Success', variant: 'success');
    }

    public function deleteCategory(int $id): void
    {
        $category = Category::findOrFail($id);
        $this->authorize('delete', $category);
        $category->delete();
        Flux::toast('Category deleted', 'Success', variant: 'success');
        $this->resetPage('categoriesPage');
    }

    public function deleteTag(int $id): void
    {
        $tag = Tag::findOrFail($id);
        $this->authorize('delete', $tag);
        $tag->delete();
        Flux::toast('Tag deleted', 'Success', variant: 'success');
        $this->resetPage('tagsPage');
    }

    public function toggleAllCategories(bool $checked): void
    {
        $ids = collect($this->categories->items())->pluck('id')->map(fn($v) => (int) $v)->all();
        $this->selectedCategoryIds = $checked ? $ids : [];
    }

    public function toggleAllTags(bool $checked): void
    {
        $ids = collect($this->tags->items())->pluck('id')->map(fn($v) => (int) $v)->all();
        $this->selectedTagIds = $checked ? $ids : [];
    }

    public function bulkDeleteCategories(): void
    {
        $this->authorize('delete', Category::class);
        $this->validate([
            'selectedCategoryIds' => ['required', 'array', 'min:1'],
            'selectedCategoryIds.*' => ['integer'],
        ]);

        $idsOnPage = collect($this->categories->items())->pluck('id')->map(fn ($v) => (int) $v)->all();
        $idsToDelete = array_values(array_intersect($this->selectedCategoryIds, $idsOnPage));

        if (! empty($idsToDelete)) {
            Category::whereIn('id', $idsToDelete)->delete();
        }

        $this->selectedCategoryIds = [];
        $this->resetPage('categoriesPage');
        Flux::toast('Selected categories deleted', 'Success', variant: 'success');
    }

    public function bulkDeleteTags(): void
    {
        $this->authorize('delete', Tag::class);
        $this->validate([
            'selectedTagIds' => ['required', 'array', 'min:1'],
            'selectedTagIds.*' => ['integer'],
        ]);

        $idsOnPage = collect($this->tags->items())->pluck('id')->map(fn ($v) => (int) $v)->all();
        $idsToDelete = array_values(array_intersect($this->selectedTagIds, $idsOnPage));

        if (! empty($idsToDelete)) {
            Tag::whereIn('id', $idsToDelete)->delete();
        }

        $this->selectedTagIds = [];
        $this->resetPage('tagsPage');
        Flux::toast('Selected tags deleted', 'Success', variant: 'success');
    }

    /**
     * Keep selections scoped to the current page when categories page changes.
     */
    public function updatedCategoriesPage(): void
    {
        $ids = collect($this->getCategoriesProperty()->items())->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->selectedCategoryIds = array_values(array_intersect($this->selectedCategoryIds, $ids));
    }

    /**
     * Keep selections scoped to the current page when tags page changes.
     */
    public function updatedTagsPage(): void
    {
        $ids = collect($this->getTagsProperty()->items())->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->selectedTagIds = array_values(array_intersect($this->selectedTagIds, $ids));
    }

    public function openEditModal(string $type, int $id): void
    {
        if (! in_array($type, ['category', 'tag'])) {
            return;
        }
        $this->editingType = $type;
        $this->editingId = $id;

        if ($type === 'category') {
            $model = Category::findOrFail($id);
        } else {
            $model = Tag::findOrFail($id);
        }

        $this->authorize('update', $model);

        $this->editingName = $model->name;
        $this->editingColor = (string) ($model->color ?? '');
    }

    public function updateEditing(): void
    {
        if ($this->editingType === 'category') {
            $model = Category::findOrFail($this->editingId);
            $this->authorize('update', $model);
            $this->validate([
                'editingName' => ['required', 'string', 'max:255', 'unique:categories,name,' . $model->id],
                'editingColor' => ['nullable', 'string', 'max:50'],
            ]);
            $model->update([
                'name' => $this->editingName,
                'slug' => Str::slug($this->editingName),
                'color' => $this->editingColor ?: null,
            ]);
        } elseif ($this->editingType === 'tag') {
            $model = Tag::findOrFail($this->editingId);
            $this->authorize('update', $model);
            $this->validate([
                'editingName' => ['required', 'string', 'max:255', 'unique:tags,name,' . $model->id],
                'editingColor' => ['nullable', 'string', 'max:50'],
            ]);
            $model->update([
                'name' => $this->editingName,
                'slug' => Str::slug($this->editingName),
                'color' => $this->editingColor ?: null,
            ]);
        } else {
            return;
        }

        Flux::modal('edit-taxonomy-modal')->close();
        Flux::toast('Saved changes', 'Success', variant: 'success');
        $this->reset(['editingId', 'editingType', 'editingName', 'editingColor']);
    }
}

?>

<div class="space-y-6">
    <flux:heading size="xl">Manage Taxonomies</flux:heading>
    <flux:description>
        Use this page to manage the site's categories and tags. You can create, edit, and delete categories and tags to organize content across blogs, announcements, and other modules. Each category and tag can have a custom color for easy identification. Changes here will immediately affect how content is grouped and filtered throughout the platform.
    </flux:description>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Categories Management -->
        <flux:card class="space-y-4">
            <flux:heading size="lg">Categories</flux:heading>
            <form wire:submit.prevent="createCategory" class="flex flex-wrap gap-3 items-end">
                <flux:field class="w-full sm:w-auto">
                    <flux:label for="category_name">Name</flux:label>
                    <flux:input id="category_name" name="name" wire:model.defer="newCategoryName" required placeholder="Name" />
                </flux:field>
                <div class="flex items-end gap-3 w-full sm:w-auto">
                    <flux:field>
                        <flux:label for="category_color">Color</flux:label>
                        <flux:input id="category_color" name="color" placeholder="#334155" pattern="^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$" title="HEX color like #334155" wire:model.live="newCategoryColor" />
                    </flux:field>
                    <flux:field>
                        <flux:label class="opacity-70">Pick</flux:label>
                        <input type="color" wire:model.live="newCategoryColor" class="h-9 w-12 rounded border border-white/10 bg-transparent p-1" aria-label="Pick category color" />
                    </flux:field>
                </div>
                <flux:text class="text-xs text-gray-400 w-full">Color accepts HEX like #334155; leave blank to use the default.</flux:text>
                @error('newCategoryName') <flux:text class="text-red-400 text-xs w-full">{{ $message }}</flux:text> @enderror
                @error('newCategoryColor') <flux:text class="text-red-400 text-xs w-full">{{ $message }}</flux:text> @enderror
                <flux:button type="submit" icon="plus" variant="primary">Create</flux:button>
            </form>

            <div class="space-y-3">
                @php
                    $categoryPageIds = collect($this->categories->items())->pluck('id')->all();
                    $selectedCategoryCountOnPage = count(array_intersect($selectedCategoryIds, $categoryPageIds));
                @endphp
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-400">
                        <span>Selected:</span>
                        <span class="font-semibold text-gray-200">{{ $selectedCategoryCountOnPage }}</span>
                    </div>
                    <flux:button type="button"
                                 size="sm"
                                 icon="trash"
                                 variant="danger"
                                 :disabled="$selectedCategoryCountOnPage === 0"
                                 x-on:click.prevent="if (confirm('Delete selected categories on this page?')) { $wire.bulkDeleteCategories() }">
                        Bulk
                    </flux:button>
                </div>
                <flux:table :paginate="$this->categories">
                    <flux:table.columns>
                        <flux:table.column>
                            @php
                                $categoryPageIds = collect($this->categories->items())->pluck('id')->all();
                                $categoryOnPageSelectedCount = count(array_intersect($selectedCategoryIds, $categoryPageIds));
                                $categoryAllOnPageSelected = $categoryOnPageSelectedCount === count($categoryPageIds) && count($categoryPageIds) > 0;
                            @endphp
                            <input type="checkbox"
                                   aria-label="Select all categories"
                                   wire:key="categories-header-{{ implode('-', $categoryPageIds) }}-{{ count($selectedCategoryIds) }}"
                                   wire:change="toggleAllCategories($event.target.checked)"
                                   @checked($categoryAllOnPageSelected)>
                        </flux:table.column>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>Color</flux:table.column>
                        <flux:table.column>Preview</flux:table.column>
                        <flux:table.column>Actions</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($this->categories as $category)
                            <flux:table.row :key="$category->id" class="align-middle">
                                <flux:table.cell>
                                    <input class="category-checkbox"
                                           type="checkbox"
                                           value="{{ $category->id }}"
                                           aria-label="Select category {{ $category->name }}"
                                           wire:key="category-row-{{ $category->id }}-{{ in_array($category->id, $selectedCategoryIds) ? '1' : '0' }}"
                                           wire:model="selectedCategoryIds">
                                </flux:table.cell>
                                <flux:table.cell>{{ $category->name }}</flux:table.cell>
                                <flux:table.cell>
                                    <span class="inline-flex items-center gap-2">
                                        <span class="h-3 w-3 rounded-full border border-white/20" style="background-color: {{ filled($category->color) ? $category->color : '#334155' }}"></span>
                                        <span>{{ filled($category->color) ? $category->color : 'N/A' }}</span>
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if (filled($category->color))
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-white/10"
                                              style="background-color: {{ $category->color }}; color: #fff;">
                                            {{ $category->name }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-white/5 text-white/70 ring-1 ring-white/10">
                                            No color
                                        </span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="space-x-2">
                                    <flux:modal.trigger name="edit-taxonomy-modal" wire:click="openEditModal('category', {{ $category->id }})">
                                        <flux:button size="xs" icon="pencil-square" variant="primary" color="sky" title="Edit Category"></flux:button>
                                    </flux:modal.trigger>
                                    <flux:button type="button" size="xs" icon="trash" variant="danger" x-on:click.prevent="if(confirm('Are you sure you want to delete this category? This cannot be undone.')) { $wire.deleteCategory({{ $category->id }}) }" title="Delete Category"></flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center text-gray-500">No categories found</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>

        <!-- Tags Management -->
        <flux:card class="space-y-4">
            <flux:heading size="lg">Tags</flux:heading>
            <form wire:submit.prevent="createTag" class="flex flex-wrap gap-3 items-end">
                <flux:field class="w-full sm:w-auto">
                    <flux:label for="tag_name">Name</flux:label>
                    <flux:input id="tag_name" name="name" wire:model.defer="newTagName" required placeholder="Name" />
                </flux:field>
        <div class="flex items-end gap-3 w-full sm:w-auto">
                    <flux:field>
                        <flux:label for="tag_color">Color</flux:label>
            <flux:input id="tag_color" name="color" placeholder="#64748b" pattern="^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$" title="HEX color like #64748b" wire:model.live="newTagColor" />
                    </flux:field>
                    <flux:field>
                        <flux:label class="opacity-70">Pick</flux:label>
            <input type="color" wire:model.live="newTagColor" class="h-9 w-12 rounded border border-white/10 bg-transparent p-1" aria-label="Pick tag color" />
                    </flux:field>
                </div>
                <flux:text class="text-xs text-gray-400 w-full">Color accepts HEX like #64748b; leave blank to use the default.</flux:text>
                @error('newTagName') <flux:text class="text-red-400 text-xs w-full">{{ $message }}</flux:text> @enderror
                @error('newTagColor') <flux:text class="text-red-400 text-xs w-full">{{ $message }}</flux:text> @enderror
                <flux:button type="submit" icon="plus" variant="primary">Create</flux:button>
            </form>

            <div class="space-y-3">
                @php
                    $tagPageIds = collect($this->tags->items())->pluck('id')->all();
                    $selectedTagCountOnPage = count(array_intersect($selectedTagIds, $tagPageIds));
                @endphp
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-400">
                        <span>Selected:</span>
                        <span class="font-semibold text-gray-200">{{ $selectedTagCountOnPage }}</span>
                    </div>
                    <flux:button type="button"
                                 size="sm"
                                 icon="trash"
                                 variant="danger"
                                 :disabled="$selectedTagCountOnPage === 0"
                                 x-on:click.prevent="if (confirm('Delete selected tags on this page?')) { $wire.bulkDeleteTags() }">
                        Bulk
                    </flux:button>
                </div>
                <flux:table :paginate="$this->tags">
                    <flux:table.columns>
                        <flux:table.column>
                            @php
                                $tagPageIds = collect($this->tags->items())->pluck('id')->all();
                                $tagOnPageSelectedCount = count(array_intersect($selectedTagIds, $tagPageIds));
                                $tagAllOnPageSelected = $tagOnPageSelectedCount === count($tagPageIds) && count($tagPageIds) > 0;
                            @endphp
                            <input type="checkbox"
                                   aria-label="Select all tags"
                                   wire:key="tags-header-{{ implode('-', $tagPageIds) }}-{{ count($selectedTagIds) }}"
                                   wire:change="toggleAllTags($event.target.checked)"
                                   @checked($tagAllOnPageSelected)>
                        </flux:table.column>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>Color</flux:table.column>
                        <flux:table.column>Preview</flux:table.column>
                        <flux:table.column>Actions</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($this->tags as $tag)
                            <flux:table.row :key="$tag->id" class="align-middle">
                                <flux:table.cell>
                                    <input class="tag-checkbox"
                                           type="checkbox"
                                           value="{{ $tag->id }}"
                                           aria-label="Select tag {{ $tag->name }}"
                                           wire:key="tag-row-{{ $tag->id }}-{{ in_array($tag->id, $selectedTagIds) ? '1' : '0' }}"
                                           wire:model="selectedTagIds">
                                </flux:table.cell>
                                <flux:table.cell>{{ $tag->name }}</flux:table.cell>
                                <flux:table.cell>
                                    <span class="inline-flex items-center gap-2">
                                        <span class="h-3 w-3 rounded-full border border-white/20" style="background-color: {{ filled($tag->color) ? $tag->color : '#64748b' }}"></span>
                                        <span>{{ filled($tag->color) ? $tag->color : 'N/A' }}</span>
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if (filled($tag->color))
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-white/10"
                                              style="background-color: {{ $tag->color }}; color: #fff;">
                                            {{ $tag->name }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-white/5 text-white/70 ring-1 ring-white/10">
                                            No color
                                        </span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="space-x-2">
                                    <flux:modal.trigger name="edit-taxonomy-modal" wire:click="openEditModal('tag', {{ $tag->id }})">
                                        <flux:button size="xs" icon="pencil-square" variant="primary" color="sky" title="Edit Tag"></flux:button>
                                    </flux:modal.trigger>
                                    <flux:button type="button" size="xs" icon="trash" variant="danger" x-on:click.prevent="if(confirm('Are you sure you want to delete this tag? This cannot be undone.')) { $wire.deleteTag({{ $tag->id }}) }" title="Delete Tag"></flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center text-gray-500">No tags found</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>


            </div>
        </flux:card>
    </div>

    <!-- Edit Taxonomy Modal -->
    <flux:modal name="edit-taxonomy-modal" title="Edit" variant="flyout" class="space-y-6">
        <flux:heading size="xl">Edit {{ $editingType === 'category' ? 'Category' : ($editingType === 'tag' ? 'Tag' : 'Taxonomy') }}</flux:heading>

        <form wire:submit.prevent="updateEditing">
            <div class="space-y-4">
                <flux:input id="edit-name" label="Name" wire:model.defer="editingName" required />
                <div class="flex items-end gap-3">
                    <flux:field class="flex-1">
                        <flux:label for="edit-color">Color</flux:label>
                        <flux:input id="edit-color" placeholder="#334155" wire:model.live="editingColor" />
                    </flux:field>
                    <flux:field>
                        <flux:label class="opacity-70">Pick</flux:label>
                        <input type="color" wire:model.live="editingColor" class="h-9 w-12 rounded border border-white/10 bg-transparent p-1" aria-label="Pick color" />
                    </flux:field>
                </div>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
