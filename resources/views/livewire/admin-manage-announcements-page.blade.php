<?php

use App\Models\{Announcement, Category, Comment, Role, Tag, User};
use Livewire\{WithPagination};
use Livewire\Volt\{Component};

new class extends Component {
    use WithPagination;

    public $sortBy = 'created_at';
    public $sortDirection = 'desc';

    public function sort($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function getAnnouncementsProperty()
    {
        return Announcement::orderBy($this->sortBy, $this->sortDirection)
            ->with(['author.roles', 'tags', 'categories', 'comments'])
            ->paginate(10);
    }

    public function mount()
    {
        $this->announcements = $this->getAnnouncementsProperty();
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Manage Announcements</flux:heading>
    <flux:description>
        Use this page to create, edit, publish, and organize announcements. You can manage announcement content, assign categories and tags, and control publication status. Actions here help keep your community informed and engaged with timely updates and news.
    </flux:description>

    <flux:table :paginate="$this->announcements">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'title'" :direction="$sortDirection" wire:click="sort('title')">Title</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'author_id'" :direction="$sortDirection" wire:click="sort('author_id')">Author</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'tags'" :direction="$sortDirection" wire:click="sort('tags')">Tags</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'categories'" :direction="$sortDirection" wire:click="sort('categories')">Categories</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Created At</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'updated_at'" :direction="$sortDirection" wire:click="sort('updated_at')">Updated At</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'is_published'" :direction="$sortDirection" wire:click="sort('is_published')">Published</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'published_at'" :direction="$sortDirection" wire:click="sort('published_at')">
                Published At
            </flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->announcements as $announcement)
                <flux:table.row :key="$announcement->id">
                    <flux:table.cell>{{ $announcement->title }}</flux:table.cell>
                    <flux:table.cell class="flex items-center gap-3">
                        @php($author = $announcement->author) {{-- may be null --}}
                        @if($author)
                            @if(!empty($author->avatar))
                                <flux:avatar size="xs" src="{{ $author->avatar }}" />
                            @endif
                            <flux:link href="{{ route('profile.show', ['user' => $author]) }}">
                                {{ $author->name }}
                            </flux:link>
                        @else
                            <flux:text class="text-gray-500">Unknown</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $announcement->tagsAsString() }}</flux:table.cell>
                    <flux:table.cell>{{ $announcement->categoriesAsString() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:icon name="calendar" class="inline-block w-4 h-4 mr-1" />
                        {{ $announcement->created_at->diffForHumans() }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $announcement->updated_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>{{ $announcement->is_published ? 'Yes' : 'No' }}</flux:table.cell>
                    <flux:table.cell>{{ $announcement->published_at ? $announcement->published_at->format('F j, Y H:i') : 'N/A' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button wire:navigate href="{{ route('announcements.show', $announcement->id) }}" size="xs" icon="eye" title="View Announcement"></flux:button>
                        <flux:button wire:navigate href="{{ route('acp.announcements.edit', $announcement->id) }}" size="xs" icon="pencil-square" variant="primary" color="amber" title="Edit Announcement"></flux:button>
                        <flux:button wire:navigate href="{{ route('announcements.show', $announcement->id) }}" size="xs" icon="trash" variant="danger" title="Delete Announcement"></flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-gray-500">No announcements found</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="w-full text-right">
        <flux:button href="{{ route('acp.announcements.create') }}" variant="primary">Create Announcement</flux:button>
    </div>
</div>
