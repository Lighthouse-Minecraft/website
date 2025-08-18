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
use Livewire\Volt\{Component};

new class extends Component {
    public Blog $blog;
    public string $blogTitle = '';
    public string $blogContent = '';
    public array $selectedTags = [];
    public array $selectedCategories = [];
    public bool $isPublished = false;
    public ?Carbon $published_at = null;
    public bool $isPublic = false;

    public function mount(Blog $blog)
    {
        $this->blog = $blog;
        $this->blogTitle = $blog->title;
        $this->blogContent = $blog->content;
        $this->isPublished = (bool) $blog->is_published;
        $this->published_at = $blog->published_at;
        $this->isPublic = (bool) $blog->is_public;
        $this->selectedTags = $blog->tags->pluck('id')->toArray();
        $this->selectedCategories = $blog->categories->pluck('id')->toArray();
    }

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

    public function updateBlog()
    {
        // Validate incoming data
        $this->validate([
            'blogTitle' => 'required|string|max:255',
            'blogContent' => 'required|string|max:5000',
            'selectedTags' => 'array',
            'selectedCategories' => 'array',
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

        $this->blog->update([
            'title' => $this->blogTitle,
            'content' => $this->blogContent,
            'author_id' => auth()->id(),
            'is_published' => $this->isPublished,
            'published_at' => $this->isPublished ? ($this->published_at ?? now()) : null,
            'is_public' => $this->isPublic,
        ]);

        // Sync pivot relations
        $this->blog->tags()->sync($this->selectedTags);
        $this->blog->categories()->sync($this->selectedCategories);

        Flux::toast('Blog updated successfully!', 'Success', variant: 'success');
        return redirect()->route('acp.index', ['tab' => 'blog-manager']);
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Edit Blog</flux:heading>
    <form wire:submit.prevent="updateBlog">
        <div class="space-y-6">
            <flux:input label="Blog Title" wire:model="blogTitle" placeholder="Enter the title of the blog" />

            <flux:editor label="Blog Content" wire:model="blogContent" />

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

            {{-- <flux:input label="Published At" wire:model="published_at" type="datetime-local" /> --}}

            <div class="w-full text-right">
                <flux:button wire:navigate href="{{ route('acp.index', ['tab' => 'blog-manager']) }}" class="mx-4">Cancel</flux:button>
                <flux:button wire:click="updateBlog" icon="document-check" variant="primary">Update Blog</flux:button>
            </div>
        </div>
    </form>
</div>
