<?php

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $statusFilter = '';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $query = BlogPost::withTrashed()
            ->with(['author', 'category'])
            ->withCount('tags');

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $query->orderBy($this->sortBy, $this->sortDirection);

        return [
            'posts' => $query->paginate(20),
            'statuses' => BlogPostStatus::cases(),
        ];
    }
}; ?>

<div class="space-y-6 w-full">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Blog Posts</flux:heading>
        <flux:button href="{{ route('blog.manage') }}" variant="primary" size="sm" icon="pencil-square" wire:navigate>
            Blog Editor
        </flux:button>
    </div>

    <div class="flex gap-3">
        <flux:select wire:model.live="statusFilter" placeholder="All Statuses" size="sm" class="w-48">
            <flux:select.option value="">All Statuses</flux:select.option>
            @foreach($statuses as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$posts">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'title'" :direction="$sortDirection" wire:click="sort('title')">Title</flux:table.column>
            <flux:table.column>Author</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
            <flux:table.column>Category</flux:table.column>
            <flux:table.column>Tags</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'published_at'" :direction="$sortDirection" wire:click="sort('published_at')">Published</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Created</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse($posts as $post)
                <flux:table.row wire:key="blog-post-{{ $post->id }}" class="{{ $post->trashed() ? 'opacity-50' : '' }}">
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            {{ $post->title }}
                            @if($post->trashed())
                                <flux:badge color="red" size="sm">Deleted</flux:badge>
                            @endif
                            @if($post->is_edited)
                                <flux:badge color="amber" size="sm">Edited</flux:badge>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:link href="{{ route('profile.show', $post->author) }}" wire:navigate>
                            {{ $post->author->name }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="{{ $post->status->color() }}" size="sm">{{ $post->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $post->category?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $post->tags_count }}</flux:table.cell>
                    <flux:table.cell>{{ $post->published_at?->format('M j, Y') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $post->created_at->format('M j, Y') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500">No blog posts found.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
