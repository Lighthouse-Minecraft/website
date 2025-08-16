<?php

use App\Models\Category;
use App\Models\Tag;
use Livewire\Volt\{Component};

new class extends Component {
    public $blog;

    public function mount($blog)
    {
        $this->blog = $blog;
    }

}; ?>

<div class="w-full space-y-6">
    @php
        $user = auth()->user();
        $isAdmin = $user && $user->is_admin;
        $isAuthor = $user && $blog->author_id === $user->id;
    @endphp
    @if($isAdmin || $isAuthor)
        <div class="bg-red-900/30 border-l-4 border-red-500 text-red-300 px-4 py-3 shadow-sm rounded">
            <span>
                <strong class="font-semibold text-red-200">Warning:</strong>
                You are about to delete this blog post.
            </span>
            @if($blog->is_public)
                <br>
                <p class="text-sm text-red-200">This blog is currently public. Deleting it will remove it from public view.</p>
            @endif
        </div>
        <div class="bg-blue-900/30 border-l-4 border-blue-500 text-blue-300 px-4 py-3 shadow-sm rounded">
            <span class="text-sm text-blue-200">Use the buttons on the right-hand side to edit or confirm deletion, or use the back button to go back.</span>
        </div>
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ $blog->title }}</flux:heading>
            <div>
                @can('update', $blog)
                    <a href="{{ route('acp.blogs.edit', $blog->id) }}">
                        <flux:button size="xs" icon="pencil-square"></flux:button>
                    </a>
                @endcan
                @can('delete', $blog)
                    <form action="{{ route('acp.blogs.delete', $blog->id) }}" method="POST" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" size="xs" icon="trash" variant="danger"></flux:button>
                    </form>
                @endcan
            </div>
        </div>
    @else
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ $blog->title }}</flux:heading>
        </div>
    @endif

    <div id="editor_content" class="prose max-w-none">
        {!! $blog->content !!}
    </div>
    <flux:separator />
    <livewire:blogs.author-info :blog="$blog" />
    <livewire:blogs.categories :blog="$blog" />
    <livewire:blogs.tags :blog="$blog" />
    <livewire:blogs.comments :blog="$blog" />

    <div class="mb-4">
        <strong>Tags:</strong>
        @forelse($blog->tags as $tag)
            <span class="inline-block bg-gray-200 text-gray-700 px-2 py-1 rounded mr-1">{{ $tag->name }}</span>
        @empty
            <span class="text-gray-400">No tags</span>
        @endforelse
        @if($isAdmin)
            <form method="POST" action="{{ route('acp.blogs.addTag', $blog->id) }}" class="inline-block ml-2">
                @csrf
                <input type="text" name="tag" placeholder="Create new tag" required>
                <button type="submit" class="px-2 py-1 bg-green-600 text-white rounded">Create</button>
            </form>
            <form method="POST" action="{{ route('acp.blogs.removeTag', $blog->id) }}" class="inline-block ml-2">
                @csrf
                <select name="tag_id">
                    <option value="" >Select tag</option>
                    @foreach($blog->tags as $tag)
                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-2 py-1 bg-red-600 text-white rounded">Remove</button>
            </form>
        @endif
        <form method="POST" action="{{ route('acp.blogs.attachTag', $blog->id) }}" class="inline-block ml-2">
            @csrf
            <select name="tag_id">
                <option value="" >Select tag</option>
                @foreach(Tag::all() as $tag)
                    <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="mb-4">
        <strong>Categories:</strong>
        @forelse($blog->categories as $category)
            <span class="inline-block bg-blue-200 text-blue-700 px-2 py-1 rounded mr-1">{{ $category->name }}</span>
        @empty
            <span class="text-gray-400">No categories</span>
        @endforelse
        @if($isAdmin)
            <form method="POST" action="{{ route('acp.blogs.addCategory', $blog->id) }}" class="inline-block ml-2">
                @csrf
                <input type="text" name="category" placeholder="Create new category" required>
                <button type="submit" class="px-2 py-1 bg-green-600 text-white rounded">Create</button>
            </form>
            <form method="POST" action="{{ route('acp.blogs.removeCategory', $blog->id) }}" class="inline-block ml-2">
                @csrf
                <select name="category_id">
                    <option value="">Select category</option>
                    @foreach($blog->categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-2 py-1 bg-red-600 text-white rounded">Remove</button>
            </form>
        @endif
        <form method="POST" action="{{ route('acp.blogs.attachCategory', $blog->id) }}" class="inline-block ml-2">
            @csrf
            <select name="category_id">
                <option value="">Select category</option>
                @foreach(Category::all() as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="mt-6">
        <strong>Comments:</strong>
        <ul>
            @php
                // Auto-approve comments older than 24 hours if not already approved
                foreach($blog->comments as $comment) {
                    if ($comment->status !== 'approved' && $comment->created_at->lt(now()->subDay())) {
                        $comment->status = 'approved';
                        $comment->save();
                    }
                }
                $approvedComments = $blog->comments->where('status', 'approved');
            @endphp
            @forelse($approvedComments as $comment)
                <li class="mb-2 border-b pb-2">
                    <div class="text-xs text-gray-500">By {{ $comment->author->name ?? 'Unknown' }} on {{ $comment->created_at->format('M d, Y H:i') }}</div>
                    <div>{!! $comment->content !!}</div>
                </li>
            @empty
                <li class="text-gray-400">No comments yet.</li>
            @endforelse
        </ul>
    </div>

    <flux:button size="sm" icon="arrow-left" onclick="window.history.back();"></flux:button>
</div>
