<x-layouts.app>
    <flux:header class="text-2xl font-bold">Comments Index</flux:header>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="bg-zinc-900 border border-purple-900 rounded-xl p-6 shadow-lg space-y-4">
            <flux:heading>Announcement Comments</flux:heading>
            @forelse($announcementComments as $comment)
                <div class="bg-zinc-800 border border-purple-900 rounded-lg p-4 flex items-center justify-between mb-4">
                    <div>
                        <div class="text-white font-semibold">{{ $comment->commentable->title ?? 'Untitled Announcement' }}</div>
                        <div class="text-purple-300 text-sm">{!! Str::limit($comment->commentable->content ?? '', 120) !!}</div>
                        <div class="mt-2 text-sm text-gray-400"><strong>Comment:</strong> {!! $comment->content !!}</div>
                    </div>
                    <a href="{{ route('announcements.show', $comment->commentable->id ?? 0) }}" class="bg-zinc-700 text-white px-4 py-2 rounded hover:bg-zinc-600 transition">Read Full Announcement</a>
                </div>
            @empty
                <div class="text-gray-400">No announcement comments yet.</div>
            @endforelse
            <div class="mt-4">{{ $announcementComments->links() }}</div>
        </div>
        <div class="bg-zinc-900 border border-blue-900 rounded-xl p-6 shadow-lg space-y-4">
            <flux:heading>Blog Comments</flux:heading>
            @forelse($blogComments as $comment)
                <div class="bg-zinc-800 border border-blue-900 rounded-lg p-4 flex items-center justify-between mb-4">
                    <div>
                        <div class="text-white font-semibold">{{ $comment->commentable->title ?? 'Untitled Blog' }}</div>
                        <div class="text-blue-300 text-sm">{!! Str::limit($comment->commentable->content ?? '', 120) !!}</div>
                        <div class="mt-2 text-sm text-gray-400"><strong>Comment:</strong> {!! $comment->content !!}</div>
                    </div>
                    <a href="{{ route('blogs.show', $comment->commentable->id ?? 0) }}" class="bg-zinc-700 text-white px-4 py-2 rounded hover:bg-zinc-600 transition">Read Full Blog</a>
                </div>
            @empty
                <div class="text-gray-400">No blog comments yet.</div>
            @endforelse
            <div class="mt-4">{{ $blogComments->links() }}</div>
        </div>
    </div>
</x-layouts.app>
