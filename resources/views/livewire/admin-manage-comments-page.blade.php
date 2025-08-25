
<?php

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\User;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new class extends Component {
    use WithPagination;

    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    public array $selected = [];

    public function sort($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function getCommentsProperty()
    {
        return Comment::orderBy($this->sortBy, $this->sortDirection)
            ->with(['author'])
            ->paginate(10);
    }

    public function mount()
    {
        $this->comments = $this->getCommentsProperty();
    }

    /**
     * Toggle selection for all rows on the current page only.
     */
    public function toggleSelectPage(): void
    {
        $pageIds = collect($this->comments->items())->pluck('id')->all();
        $onPageSelectedCount = count(array_intersect($this->selected, $pageIds));
        $allOnPageSelected = $onPageSelectedCount === count($pageIds) && count($pageIds) > 0;

        if ($allOnPageSelected) {
            // Unselect current page
            $this->selected = array_values(array_diff($this->selected, $pageIds));
        } else {
            // Select all on current page
            $this->selected = array_values(array_unique(array_merge($this->selected, $pageIds)));
        }
    }

    /**
     * Bulk delete only selected IDs that are visible on the current page.
     */
    public function bulkDelete(): void
    {
        $pageIds = collect($this->comments->items())->pluck('id')->all();
        $idsToDelete = array_values(array_intersect($this->selected, $pageIds));

        if (empty($idsToDelete)) {
            return;
        }

        $comments = Comment::query()->whereIn('id', $idsToDelete)->get();
        $deletableIds = $comments->filter(fn ($c) => auth()->user()?->can('delete', $c))->pluck('id')->all();

        if (! empty($deletableIds)) {
            Comment::query()->whereIn('id', $deletableIds)->delete();
        }

        $this->selected = [];
        $this->resetPage();
        $this->comments = $this->getCommentsProperty();
    }

    /**
     * When the pagination page changes, drop any selections not on the new page.
     */
    public function updatedPage(): void
    {
        $currentPageIds = collect($this->getCommentsProperty()->items())->pluck('id')->all();
        $this->selected = array_values(array_intersect($this->selected, $currentPageIds));
    }
};

?>

<div class="space-y-2">
    <flux:heading size="xl">Manage Comments</flux:heading>
    <flux:description>
        Use this page to review, moderate, and manage all user comments across blogs, announcements, and other content. You can approve, edit, or delete comments to ensure community standards are met. Actions here help maintain a positive and constructive environment for all users.
    </flux:description>

    <div class="flex items-center justify-between">
        <div class="text-sm text-gray-400">
            <span>Selected:</span> <span class="font-semibold text-gray-200">{{ count($selected) }}</span>
        </div>
        <form wire:submit.prevent="bulkDelete">
            <flux:button type="submit"
                         size="sm"
                         icon="trash"
                         variant="danger"
                         :disabled="count($selected) === 0"
                         onclick="return confirm('Delete selected comments on this page?')">
                Bulk
            </flux:button>
        </form>
    </div>

    <flux:table :paginate="$this->comments">
        <flux:table.columns>
            <flux:table.column>
                @php
                    $pageIds = collect($this->comments->items())->pluck('id')->all();
                    $onPageSelectedCount = count(array_intersect($selected, $pageIds));
                    $allOnPageSelected = $onPageSelectedCount === count($pageIds) && count($pageIds) > 0;
                @endphp
                <input type="checkbox"
                       wire:key="select-header-{{ implode('-', $pageIds) }}-{{ count($selected) }}"
                       wire:click="toggleSelectPage"
                       @checked($allOnPageSelected) />
            </flux:table.column>
            <flux:table.column>Post</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'content'" :direction="$sortDirection" wire:click="sort('content')" style="min-width: 400px; max-width: 900px; width: 40%;">Comment</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'author_id'" :direction="$sortDirection" wire:click="sort('author_id')">Author</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Created At</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'updated_at'" :direction="$sortDirection" wire:click="sort('updated_at')">Updated At</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->comments as $comment)
                <flux:table.row :key="$comment->id" class="break-words align-middle">
                    <flux:table.cell>
                        <input type="checkbox"
                               class="rounded"
                               value="{{ $comment->id }}"
                               wire:key="row-select-{{ $comment->id }}-{{ in_array($comment->id, $selected) ? '1' : '0' }}"
                               wire:model="selected" />
                    </flux:table.cell>
                    <flux:table.cell class="w-auto">
                        @php
                            $rawType = (string) ($comment->getRawOriginal('commentable_type') ?? '');
                            $normalized = strtolower(class_basename($rawType));
                            if (! in_array($normalized, ['blog', 'announcement'], true)) {
                                $normalized = strtolower($rawType);
                            }

                            $parent = null;
                            $isDeleted = false;
                            if ($normalized === 'blog') {
                                $parent = Blog::withTrashed()->find($comment->commentable_id);
                                $isDeleted = $parent ? (method_exists($parent, 'trashed') && $parent->trashed()) : true;
                            } elseif ($normalized === 'announcement') {
                                $parent = Announcement::withTrashed()->find($comment->commentable_id);
                                $isDeleted = $parent ? (method_exists($parent, 'trashed') && $parent->trashed()) : true;
                            }
                        @endphp

                        <div class="flex flex-col">
                            <div class="flex items-center gap-2">
                                <span class="text-xs uppercase tracking-wide text-gray-400">{{ ucfirst($normalized ?: 'Unknown') }}</span>
                                @if($isDeleted)
                                    <span class="inline-block px-1 py-0.5 text-[10px] font-semibold bg-red-700 text-white rounded align-middle">Deleted</span>
                                @endif
                            </div>
                            <div class="mt-0.5 leading-tight max-w-[48ch]">
                                @if($parent && ! $isDeleted)
                                    @if($normalized === 'blog')
                                        <flux:link wire:navigate href="{{ route('blogs.show', ['id' => $parent->id, 'from' => 'acp']) }}" class="font-medium truncate block" title="{{ $parent->title }}">{{ $parent->title }}</flux:link>
                                    @elseif($normalized === 'announcement')
                                        <flux:link wire:navigate href="{{ route('announcements.show', ['id' => $parent->id, 'from' => 'acp']) }}" class="font-medium truncate block" title="{{ $parent->title }}">{{ $parent->title }}</flux:link>
                                    @endif
                                @elseif($parent && $isDeleted)
                                    <span class="font-medium text-gray-300 truncate block" title="{{ $parent->title }}">{{ $parent->title }}</span>
                                @else
                                    @php $fallbackTitle = $comment->commentable_title; @endphp
                                    @if($fallbackTitle)
                                        <span class="font-medium text-gray-300 truncate block" title="{{ $fallbackTitle }}">{{ $fallbackTitle }}</span>
                                    @else
                                        <span class="font-medium text-gray-400">Unknown</span>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell style="break-words; max-width: 900px; padding-top: 6px; padding-bottom: 6px;">
                            <div
                                class="prose max-w-none break-words text-justify hyphens-auto leading-[1.45]
                                       [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:max-w-full [&_pre]:w-full [&_pre]:overflow-x-auto
                                       [&_code]:break-words"
                                style="max-width: 900px; margin-bottom: 0; max-height: calc(1.45em * 4); overflow: hidden;"
                            >
                                {!! $comment->content !!}
                            </div>
                        <br>
                        <div>
                            @if($comment->needs_review)
                                <span class="inline-block ml-2 px-1 py-0.5 text-xs font-semibold bg-yellow-600 text-white rounded align-middle">Needs Review</span>
                            @endif
                            @if($comment->reviewed_by)
                                <span class="inline-block ml-2 px-1 py-0.5 text-xs font-semibold bg-green-700 text-white rounded align-middle">Reviewed by
                                    <flux:link href="{{ route('profile.show', ['user' => $comment->reviewer?->id]) }}">
                                        {{ $comment->reviewer?->name ?? 'Unknown' }}
                                    </flux:link>
                                    on @if($comment->reviewed_at)
                                        <time class="comment-ts" datetime="{{ $comment->reviewed_at->toIso8601String() }}">{{ $comment->reviewed_at->format('M j, Y H:i') }}</time>
                                    @else

                                    @endif
                                </span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @php($author = $comment->author)
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
                    <flux:table.cell>
                        <flux:icon name="calendar" class="inline-block w-4 h-4 mr-1" />
                        {{ $comment->created_at->diffForHumans() }}
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $comment->updated_at->diffForHumans() }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button wire:navigate href="{{ route('comments.show', ['id' => $comment->id, 'from' => 'acp']) }}" size="xs" icon="eye" title="View Comment"></flux:button>

                        @can('review', $comment)
                            @if($comment->needs_review)
                                <form action="{{ route('acp.comments.approve', $comment->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <flux:button type="submit" size="xs" icon="check" variant="primary" color="green" title="Mark as Reviewed"></flux:button>
                                </form>
                                <form action="{{ route('acp.comments.reject', $comment->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <flux:button type="submit" size="xs" icon="no-symbol" variant="primary" color="orange" title="Reject"></flux:button>
                                </form>
                            @endif
                        @endcan

                        @can('update', $comment)
                            <flux:button wire:navigate href="{{ route('acp.comments.edit', $comment->id) }}" size="xs" icon="pencil-square" variant="primary" color="sky" title="Edit Comment"></flux:button>
                        @endcan

                        @can('delete', $comment)
                            <flux:button wire:navigate href="{{ route('acp.comments.confirmDelete', ['id' => $comment->id, 'from' => 'acp']) }}" size="xs" icon="trash" variant="danger" title="Delete Comment"></flux:button>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-gray-500">No comments found</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="w-full text-right">
        <flux:button href="{{ route('acp.comments.create') }}" variant="primary">Create Comment</flux:button>
    </div>

    <style>
        .badge-timer {
            display: inline-block;
            min-width: 2.5em;
            padding: 0.25em 0.7em;
            font-size: 0.95em;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(90deg, #3b82f6 60%, #2563eb 100%);
            border-radius: 999px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.07);
            cursor: default;
            transition: background 0.2s;
        }
        .badge-timer[title]:hover {
            background: linear-gradient(90deg, #2563eb 60%, #3b82f6 100%);
        }
        .badge-timer:empty {
            visibility: hidden;
        }
    </style>

    <script>
        function formatTimer(seconds) {
            if (seconds <= 0) return 'Expired';
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            return `${h}h${h > 0 ? ' ' : ''}${m}m${m > 0 ? ' ' : ''}${s}s`;
        }

        function formatTooltip(seconds) {
            if (seconds <= 0) return 'Expired';
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            return `${h} hours, ${m} minutes, ${s} seconds left`;
        }

        function updateTimers() {
            const now = Math.floor(Date.now() / 1000);
            document.querySelectorAll('.timer').forEach(function(el) {
                const created = parseInt(el.getAttribute('data-created'));
                const end = created + 24 * 3600;
                const left = end - now;
                el.textContent = left > 0 ? `${Math.ceil(left / 3600)}h` : 'Expired';
                el.title = formatTooltip(left);
            });
        }
        setInterval(updateTimers, 1000);
        window.addEventListener('DOMContentLoaded', updateTimers);
    </script>
</div>
