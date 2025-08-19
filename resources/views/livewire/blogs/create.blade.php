<?php

use App\Models\Blog;
use App\Models\Category;
use App\Models\Tag;
use Flux\Flux;
use Flux\Option;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades;
use Livewire\Volt\Component;

new class extends Component {
    public string $blogTitle = '';
    public string $blogContent = '';
    public array $selectedTags = [];
    public array $selectedCategories = [];
    public bool $isPublished = false;
    public ?Carbon $published_at = null;
    public bool $isPublic = false;

    // Computed options to avoid DB queries in the view each render
    public function getTagOptionsProperty(): array
    {
        return Tag::query()
            ->get(['id', 'name'])
            ->map(fn ($t) => ['label' => $t->name, 'value' => (int) $t->id])
            ->toArray();
    }

    public function getCategoryOptionsProperty(): array
    {
        return Category::query()
            ->get(['id', 'name'])
            ->map(fn ($c) => ['label' => $c->name, 'value' => (int) $c->id])
            ->toArray();
    }

    public function saveBlog()
    {
        // Validate incoming data
        $this->validate([
            'blogTitle' => 'required|string|max:255',
            'blogContent' => 'required|string|max:5000',
            'selectedTags' => ['array'],
            'selectedTags.*' => ['integer', 'exists:tags,id'],
            'selectedCategories' => ['array'],
            'selectedCategories.*' => ['integer', 'exists:categories,id'],
            'isPublished' => 'boolean',
            'published_at' => 'date|nullable',
            'isPublic' => 'boolean',
        ]);

        // Force types to ensure correct data types
        $this->blogTitle = (string) $this->blogTitle;
        $this->blogContent = (string) $this->blogContent;
        $this->selectedTags = array_map('intval', Arr::wrap($this->selectedTags));
        $this->selectedCategories = array_map('intval', Arr::wrap($this->selectedCategories));
        $this->isPublished = (bool) $this->isPublished;
        $this->published_at = $this->published_at ? Carbon::parse($this->published_at) : null;
        $this->isPublic = (bool) $this->isPublic;

        $slug = Str::slug($this->blogTitle);
        $blog = Blog::create([
            'title' => $this->blogTitle,
            'slug' => $slug,
            'content' => $this->blogContent,
            'author_id' => auth()->id(),
            'is_published' => $this->isPublished,
            'published_at' => $this->isPublished ? ($this->published_at ?? now()) : null,
            'is_public' => $this->isPublic,
        ]);

        // Sync pivot relations
        $blog->tags()->sync($this->selectedTags);
        $blog->categories()->sync($this->selectedCategories);

    Flux::toast('Blog created successfully!', 'Success', variant: 'success');
        return redirect()->route('acp.index', ['tab' => 'blog-manager']);
    }
}; ?>


<div class="space-y-6">
    <flux:heading size="xl">Create New Blog</flux:heading>
    <form wire:submit.prevent="saveBlog">
        <div class="space-y-6">
            <flux:input wire:model="blogTitle" placeholder="Enter title..." class="bg-transparent text-lg font-semibold" />

            <flux:editor
                wire:model="blogContent"
                class="bg-transparent
                    [&_[data-slot=content]_.ProseMirror]:break-words
                    [&_[data-slot=content]_.ProseMirror]:whitespace-pre-wrap
                    [&_[data-slot=content]_.ProseMirror]:max-w-full
                    [&_[data-slot=content]_.ProseMirror]:overflow-x-auto
                    [&_[data-slot=content]]:max-h-[500px]
                    [&_[data-slot=content]]:overflow-y-auto
                    [&_[data-slot=content]_pre]:overflow-x-auto
                    [&_[data-slot=content]_pre]:whitespace-pre-wrap!
                    [&_[data-slot=content]_pre]:max-w-full
                    [&_[data-slot=content]_pre]:w-full
                    [&_[data-slot=content]_pre_code]:break-words!
                    [&_[data-slot=content]_pre]:rounded-md
                    [&_[data-slot=content]_pre]:p-3
                    [&_[data-slot=content]_pre]:my-3
                    [&_[data-slot=content]_pre]:border
                    [&_[data-slot=content]_pre]:bg-black/10
                    [&_[data-slot=content]_pre]:border-black/20
                    dark:[&_[data-slot=content]_pre]:bg-white/10
                    dark:[&_[data-slot=content]_pre]:border-white/20
                    [&_[data-slot=content]_pre]:font-mono
                    [&_[data-slot=content]_pre]:text-sm
                "
                style="text-align: justify;"
            />

            <flux:field>
                <flux:label>Tags</flux:label>
                <flux:select variant="listbox" multiple searchable indicator="checkbox" wire:model.live="selectedTags">
                    <x-slot name="button">
                        <flux:select.button class="w-full max-w-xl" placeholder="Select tags" :invalid="$errors->has('selectedTags')" />
                    </x-slot>
                    @foreach ($this->getTagOptionsProperty() as $tag)
                        <flux:select.option value="{{ $tag['value'] }}">{{ $tag['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="selectedTags" />
            </flux:field>

            <flux:field>
                <flux:label>Categories</flux:label>
                <flux:select variant="listbox" multiple searchable indicator="checkbox" wire:model.live="selectedCategories">
                    <x-slot name="button">
                        <flux:select.button class="w-full max-w-xl" placeholder="Select categories" :invalid="$errors->has('selectedCategories')" />
                    </x-slot>
                    @foreach ($this->getCategoryOptionsProperty() as $category)
                        <flux:select.option value="{{ $category['value'] }}">{{ $category['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="selectedCategories" />
            </flux:field>

            <flux:checkbox label="Published" wire:model="isPublished" />

            <flux:checkbox label="Public" wire:model="isPublic" />

            <div class="w-full text-right">
                <flux:button wire:click="saveBlog" icon="document-check" variant="primary">Save Blog</flux:button>
            </div>
        </div>
    </form>
</div>
