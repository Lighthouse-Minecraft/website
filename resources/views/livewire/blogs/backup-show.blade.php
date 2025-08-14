<x-layouts.app>
    <div class="w-full space-y-6">
        <div class="bg-red-900/30 border-l-4 border-red-500 text-red-300 px-4 py-3 shadow-sm rounded">
            <strong class="font-semibold text-red-200">Warning:</strong>
            You are about to delete this blog post.<br>
            <span class="text-sm text-red-200">Use the buttons on the right-hand side to edit or confirm deletion, or use the back button to go back.</span>
        </div>
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ $blog->title }}</flux:heading>
            <div>
                @can('update', $blog)
                    <a href="{{ route('admin.blogs.edit', $blog->id) }}">
                        <flux:button size="xs" icon="pencil-square"></flux:button>
                    </a>
                @endcan
                @can('delete', $blog)
                    <form action="{{ route('admin.blogs.delete', $blog->id) }}" method="POST" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" size="xs" icon="trash" variant="danger"></flux:button>
                    </form>
                @endcan
            </div>
        </div>

        <div class="text-sm text-gray-500" style="display: flex; align-items: center; gap: 8px;">
            @php $author = $blog->author; @endphp
            By
            @if($author)
                <span style="display: inline-flex; align-items: center;">
                    @if(!empty($author->avatar))
                        <flux:avatar size="xs" src="{{ $author->avatar }}" style="vertical-align: middle; margin-right: 4px;" />
                    @endif
                    <flux:link href="{{ route('profile.show', ['user' => $author]) }}">
                        {{ $author->name ?? 'Unknown' }}
                    </flux:link>
                </span>
            @else
                <span>Unknown</span>
            @endif
            <span>{{ $blog->created_at->format('M d, Y') }}</span>
            @if($blog->published_at)
                <span>&middot; Published: {{ $blog->published_at->format('M d, Y H:i') }}</span>
            @endif
        </div>

        <div class="mb-4">
            <strong>Tags:</strong>
            @forelse($blog->tags as $tag)
                <span class="inline-block bg-gray-200 text-gray-700 px-2 py-1 rounded mr-1">{{ $tag->name }}</span>
            @empty
                <span class="text-gray-400">No tags</span>
            @endforelse
        </div>
        <div class="mb-4">
            <strong>Categories:</strong>
            @forelse($blog->categories as $category)
                <span class="inline-block bg-blue-200 text-blue-700 px-2 py-1 rounded mr-1">{{ $category->name }}</span>
            @empty
                <span class="text-gray-400">No categories</span>
            @endforelse
        </div>
        <div id="editor_content" class="prose max-w-none">
            {!! $blog->content !!}
        </div>
        <div class="mt-6">
            <strong>Comments:</strong>
            <ul>
                @forelse($blog->comments as $comment)
                    <li class="mb-2 border-b pb-2">
                        <div class="text-xs text-gray-500">By {{ $comment->author->name ?? 'Unknown' }} on {{ $comment->created_at->format('M d, Y H:i') }}</div>
                        <div>{{ $comment->content }}</div>
                    </li>
                @empty
                    <li class="text-gray-400">No comments yet.</li>
                @endforelse
            </ul>
        </div>

        <flux:button size="sm" icon="arrow-left" onclick="window.history.back();"></flux:button>
    </div>
</x-layouts.app>
