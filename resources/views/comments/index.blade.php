<x-layouts.app>
    <flux:header class="text-2xl font-bold">Comments Index</flux:header>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="bg-zinc-900 border border-purple-900 rounded-xl p-6 shadow-lg space-y-4">
            <flux:heading>Announcement Comments</flux:heading>
            @forelse($announcementComments as $comment)
                <div class="bg-zinc-800 border border-purple-900 rounded-lg p-4 flex items-start justify-between gap-3 mb-4">
                    <div class="min-w-0 flex-1">
                        <div class="text-white font-semibold">{{ $comment->commentable->title ?? 'Untitled Announcement' }}</div>
                        <div class="text-purple-300 text-sm">{!! Str::limit($comment->commentable->content ?? '', 120) !!}</div>
                        <div class="mt-2 text-sm text-gray-400 break-words break-all overflow-hidden">
                            <strong>Comment:</strong>
                            <div class="prose max-w-none text-gray-200 whitespace-pre-wrap break-words break-all [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:max-w-full [&_pre]:w-full [&_pre]:overflow-x-auto [&_code]:break-words [&_code]:break-all">{!! $comment->content !!}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 self-start shrink-0">
                        <flux:button size="xs" wire:navigate href="{{ route('announcements.show', ['id' => $comment->commentable->id ?? 0, 'from' => 'index']) }}" variant="primary">Read Announcement</flux:button>
                        @can('delete', $comment)
                            <form method="POST" action="{{ route('comments.destroy', $comment->id) }}">
                                @csrf
                                @method('DELETE')
                                <flux:button size="xs" type="submit" icon="trash" variant="danger">Delete</flux:button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="text-gray-400">No announcement comments yet.</div>
            @endforelse
            <div class="mt-4">{{ $announcementComments->links() }}</div>
        </div>
        <div class="bg-zinc-900 border border-blue-900 rounded-xl p-6 shadow-lg space-y-4">
            <flux:heading>Blog Comments</flux:heading>
            @forelse($blogComments as $comment)
                <div class="bg-zinc-800 border border-blue-900 rounded-lg p-4 flex items-start justify-between gap-3 mb-4">
                    <div class="min-w-0 flex-1">
                        <div class="text-white font-semibold">{{ $comment->commentable->title ?? 'Untitled Blog' }}</div>
                        <div class="text-blue-300 text-sm">{!! Str::limit($comment->commentable->content ?? '', 120) !!}</div>
                        <div class="mt-2 text-sm text-gray-400 break-words break-all overflow-hidden">
                            <strong>Comment:</strong>
                            <div class="prose max-w-none text-gray-200 whitespace-pre-wrap break-words break-all [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:max-w-full [&_pre]:w-full [&_pre]:overflow-x-auto [&_code]:break-words [&_code]:break-all">{!! $comment->content !!}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 self-start shrink-0">
                        <flux:button size="xs" wire:navigate href="{{ route('blogs.show', ['id' => $comment->commentable->id ?? 0, 'from' => 'index']) }}" variant="primary">Read Blog</flux:button>
                        @can('delete', $comment)
                            <form method="POST" action="{{ route('comments.destroy', $comment->id) }}">
                                @csrf
                                @method('DELETE')
                                <flux:button size="xs" type="submit" icon="trash" variant="danger">Delete</flux:button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="text-gray-400">No blog comments yet.</div>
            @endforelse
            <div class="mt-4">{{ $blogComments->links() }}</div>
        </div>
    </div>
</x-layouts.app>
