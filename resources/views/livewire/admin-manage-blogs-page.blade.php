<?php

use App\Models\Blog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Livewire\WithPagination;
use Livewire\Volt\Component;

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

    public function getBlogsProperty()
    {
        return Blog::orderBy($this->sortBy, $this->sortDirection)
            ->with(['author.roles', 'tags', 'categories', 'comments'])
            ->paginate(10);
    }

    public function mount()
    {
        $this->blogs = $this->getBlogsProperty();
    }
};

?>

<div class="space-y-6">
    <flux:heading size="xl">Manage Blogs</flux:heading>
    <flux:description>
        Use this page to create, edit, publish, and organize blog posts. You can manage blog content, assign categories and tags, and control publication status. Actions here help keep your site's blog section fresh, relevant, and well-organized for your audience.
    </flux:description>

    <flux:table :paginate="$this->blogs">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'title'" :direction="$sortDirection" wire:click="sort('title')">Title</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'author_id'" :direction="$sortDirection" wire:click="sort('author_id')">Author</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'tags'" :direction="$sortDirection" wire:click="sort('tags')">Tags</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'categories'" :direction="$sortDirection" wire:click="sort('categories')">Categories</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Created At</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'updated_at'" :direction="$sortDirection" wire:click="sort('updated_at')">Updated At</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'is_published'" :direction="$sortDirection" wire:click="sort('is_published')">Published</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'published_at'" :direction="$sortDirection" wire:click="sort('published_at')">Published At</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'is_public'" :direction="$sortDirection" wire:click="sort('is_public')">Public</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->blogs as $blog)
                <flux:table.row :key="$blog->id">
                    <flux:table.cell>{{ $blog->title }}</flux:table.cell>
                    <flux:table.cell class="flex items-center gap-3">
                        @php($author = $blog->author) {{-- may be null --}}
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
                    <flux:table.cell>{{ $blog->tagsAsString() }}</flux:table.cell>
                    <flux:table.cell>{{ $blog->categoriesAsString() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:icon name="calendar" class="inline-block w-4 h-4 mr-1" />
                        {{ $blog->created_at->diffForHumans() }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $blog->updated_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>{{ $blog->is_published ? 'Yes' : 'No' }}</flux:table.cell>
                    <flux:table.cell>{{ $blog->published_at ? $blog->published_at->format('F j, Y H:i') : 'N/A' }}</flux:table.cell>
                    <flux:table.cell>{{ $blog->is_public ? 'Yes' : 'No' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button wire:navigate href="{{ route('blogs.show', $blog->id) }}" size="xs" icon="eye" title="View Blog"></flux:button>
                        @if(auth()->id() === $blog->author_id)
                            <flux:button wire:navigate href="{{ route('acp.blogs.edit', $blog->id) }}" size="xs" icon="pencil-square" variant="primary" color="amber" title="Edit Blog"></flux:button>
                        @endif
                        <flux:button wire:navigate href="{{ route('blogs.show', $blog->id) }}" size="xs" icon="trash" variant="danger" title="Delete Blog"></flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-gray-500">No blogs found</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="w-full text-right">
        <flux:button href="{{ route('acp.blogs.create') }}" variant="primary">Create Blog</flux:button>
    </div>
</div>
