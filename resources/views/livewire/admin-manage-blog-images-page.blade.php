<?php

use App\Actions\DeleteBlogImage;
use App\Models\BlogImage;
use Flux\Flux;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    private const ALLOWED_SORTS = ['title', 'created_at'];

    public function sort(string $column): void
    {
        if (! in_array($column, self::ALLOWED_SORTS)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function deleteImage(int $imageId): void
    {
        $this->authorize('manage-blog');

        $image = BlogImage::findOrFail($imageId);

        try {
            DeleteBlogImage::run($image);
            Flux::toast('Image deleted successfully.', 'Success', variant: 'success');
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');
        }
    }

    public function with(): array
    {
        $query = BlogImage::with('uploadedBy')
            ->withCount('posts');

        if ($this->search) {
            $query->where('title', 'like', '%' . $this->search . '%');
        }

        $sortBy = in_array($this->sortBy, self::ALLOWED_SORTS) ? $this->sortBy : 'created_at';
        $sortDir = in_array($this->sortDirection, ['asc', 'desc']) ? $this->sortDirection : 'desc';
        $query->orderBy($sortBy, $sortDir);

        return [
            'images' => $query->paginate(20),
        ];
    }
}; ?>

<div class="space-y-6 w-full">
    <flux:heading size="xl">Blog Images</flux:heading>

    <div class="flex gap-3">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by title..." size="sm" class="w-64" icon="magnifying-glass" />
    </div>

    <flux:table :paginate="$images">
        <flux:table.columns>
            <flux:table.column>Thumbnail</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'title'" :direction="$sortDirection" wire:click="sort('title')">Title</flux:table.column>
            <flux:table.column>Alt Text</flux:table.column>
            <flux:table.column>Uploaded By</flux:table.column>
            <flux:table.column>Usage Count</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Uploaded</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse($images as $image)
                <flux:table.row wire:key="blog-image-{{ $image->id }}">
                    <flux:table.cell>
                        <img src="{{ $image->url() }}" alt="{{ $image->alt_text }}" class="w-16 h-16 object-cover rounded" />
                    </flux:table.cell>
                    <flux:table.cell>{{ $image->title }}</flux:table.cell>
                    <flux:table.cell class="max-w-xs">
                        <span class="line-clamp-2 text-sm">{{ Str::limit($image->alt_text, 80) }}</span>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($image->uploadedBy)
                            <flux:link href="{{ route('profile.show', $image->uploadedBy) }}" wire:navigate>
                                {{ $image->uploadedBy->name }}
                            </flux:link>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($image->posts_count > 0)
                            <flux:badge color="blue" size="sm">{{ $image->posts_count }} {{ Str::plural('post', $image->posts_count) }}</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">Unused</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $image->created_at->format('M j, Y') }}</flux:table.cell>
                    <flux:table.cell>
                        @if($image->posts_count === 0)
                            <flux:button variant="danger" size="sm" icon="trash" wire:click="deleteImage({{ $image->id }})" wire:confirm="Are you sure you want to delete this image? This cannot be undone.">
                                Delete
                            </flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500">No blog images found.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
