
<?php
use App\Models\Comment;
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
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Manage Comments</flux:heading>
    <flux:description>
        Use this page to review, moderate, and manage all user comments across blogs, announcements, and other content. You can approve, edit, or delete comments to ensure community standards are met. Actions here help maintain a positive and constructive environment for all users.
    </flux:description>

    <flux:table :paginate="$this->comments">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'content'" :direction="$sortDirection" wire:click="sort('content')">Content</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'author_id'" :direction="$sortDirection" wire:click="sort('author_id')">Author</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Created At</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'updated_at'" :direction="$sortDirection" wire:click="sort('updated_at')">Updated At</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->comments as $comment)
                <flux:table.row :key="$comment->id">
                    <flux:table.cell>{!! $comment->content !!}</flux:table.cell>
                    <flux:table.cell class="flex items-center gap-3">
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
                    <flux:table.cell>{{ $comment->updated_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button wire:navigate href="{{ route('comments.show', $comment->id) }}" size="xs" icon="eye" title="View Comment"></flux:button>
                        @if($comment->status !== 'approved')
                            <form action="{{ route('acp.comments.approve', $comment->id) }}" method="POST" style="display:inline;">
                                @csrf
                                <flux:button type="submit" size="xs" icon="check" variant="primary" color="green" title="Approve Comment"></flux:button>
                            </form>
                        @endif
                        @if($comment->status !== 'rejected')
                            <form action="{{ route('acp.comments.reject', $comment->id) }}" method="POST" style="display:inline;">
                                @csrf
                                <flux:button type="submit" size="xs" icon="no-symbol" variant="primary" color="rose" title="Reject Comment"></flux:button>
                            </form>
                        @endif
                        @if(auth()->id() === $comment->author_id)
                            <flux:button wire:navigate href="{{ route('acp.comments.edit', $comment->id) }}" size="xs" icon="pencil-square" variant="primary" color="amber" title="Edit Comment"></flux:button>
                        @endif
                        <form action="{{ route('acp.comments.delete', $comment->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this comment? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <flux:button type="submit" size="xs" icon="trash" variant="danger" title="Delete Comment"></flux:button>
                        </form>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-gray-500">No comments found</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="w-full text-right">
        <flux:button href="{{ route('acp.comments.create') }}" variant="primary">Create Comment</flux:button>
    </div>
</div>
