<x-layouts.app>
    <div class="w-full space-y-6">
        @isset($status)
            <div class="bg-green-900/30 border-l-4 border-green-500 text-green-300 px-4 py-3 shadow-sm rounded">
                <strong class="font-semibold text-green-200">Success:</strong>
                {{ $status }}
            </div>
            
            @if(request('from') === 'acp' || request()->routeIs('acp.*'))
                <script>
                    setTimeout(function () {
                        window.location = "{{ route('acp.index', ['tab' => $tab ?? 'comment-manager']) }}";
                    }, 1200);
                </script>
            @else
                <script>
                    setTimeout(function () {
                        if (document.referrer) {
                            window.history.back();
                        } else {
                            @if(request('from') === 'dashboard')
                                window.location = "{{ route('dashboard') }}";
                            @else
                                window.location = "{{ route('comments.index') }}";
                            @endif
                        }
                    }, 1200);
                </script>
            @endif
        @endisset

        <div class="bg-red-900/30 border-l-4 border-red-500 text-red-300 px-4 py-3 shadow-sm rounded">
            <strong class="font-semibold text-red-200">Warning:</strong>
            You are about to delete this comment.<br>
            <span class="text-sm text-red-200">Use the buttons on the right-hand side to edit or confirm deletion, or use the back button to go back.</span>
        </div>

        <div class="flex items-center justify-between">
            <flux:heading size="xl">Comment</flux:heading>
            <div>
                @can('update', $comment)
                    <a href="{{ route('acp.comments.edit', $comment->id) }}">
                        <flux:button size="xs" icon="pencil-square"></flux:button>
                    </a>
                @endcan
                @can('delete', $comment)
                    <form action="{{ route('acp.comments.delete', $comment->id) }}" method="POST" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        @if(request('from'))
                            <input type="hidden" name="from" value="{{ request('from') }}">
                        @endif
                        <flux:button type="submit" size="xs" icon="trash" variant="danger"></flux:button>
                    </form>
                @endcan
            </div>
        </div>

        <div class="prose max-w-none whitespace-pre-wrap break-words [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:max-w-full [&_pre]:w-full [&_pre]:overflow-x-auto [&_code]:break-words [&_code]:break-all" id="editor_content">
            {!! $comment->content !!}
        </div>

        <div class="text-sm text-gray-500 flex items-center gap-2">
            @php $author = $comment->author; @endphp
            <span>By</span>
            @if($author)
                <span class="inline-flex items-center">
                    @if(!empty($author->avatar))
                        <flux:avatar size="xs" src="{{ $author->avatar }}" class="mr-1" />
                    @endif
                    <flux:link href="{{ route('profile.show', ['user' => $author]) }}">
                        {{ $author->name ?? 'Unknown' }}
                    </flux:link>
                </span>
            @else
                <span>Unknown</span>
            @endif
            <span>on {{ $comment->created_at->format('m/d/y') }} @ {{ $comment->created_at->format('H:i') }}</span>
        </div>

        @php
            $rawType = (string) ($comment->getRawOriginal('commentable_type') ?? '');
            $normalized = strtolower(class_basename($rawType));
            if (! in_array($normalized, ['blog', 'announcement'], true)) {
                $normalized = strtolower($rawType);
            }
            $parent = null;
            if ($normalized === 'blog') {
                $parent = \App\Models\Blog::find($comment->commentable_id);
            } elseif ($normalized === 'announcement') {
                $parent = \App\Models\Announcement::withTrashed()->find($comment->commentable_id);
            }
            $parentType = $parent ? $normalized : null;
        @endphp

        <div class="mt-6 space-y-4">
            <div class="text-sm text-gray-500 flex items-center gap-2">
                <strong>On:</strong>
                @php
                    $isDeleted = false;
                    if ($parentType === 'blog' && $parent) {
                        $isDeleted = method_exists($parent, 'trashed') && $parent->trashed();
                    } elseif ($parentType === 'announcement') {
                        $isDeleted = $parent ? (method_exists($parent, 'trashed') && $parent->trashed()) : true;
                    }
                @endphp
                @if($parent)
                    @if($parentType === 'blog')
                        <flux:link href="{{ route('blogs.show', $parent->id) }}">Blog: {{ $parent->title }}</flux:link>
                    @elseif($parentType === 'announcement')
                        <flux:link href="{{ route('announcements.show', $parent->id) }}">Announcement: {{ $parent->title }}</flux:link>
                    @endif
                    @if($isDeleted)
                        <span class="inline-block ml-1 px-1 py-0.5 text-[10px] font-semibold bg-red-700 text-white rounded align-middle">Deleted</span>
                    @endif
                @else
                    <span class="text-gray-400">Unknown</span>
                    <span class="inline-block ml-1 px-1 py-0.5 text-[10px] font-semibold bg-red-700 text-white rounded align-middle">Deleted</span>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-3">
            @if(request('from') === 'acp' || request()->routeIs('acp.*'))
                <flux:button wire:navigate href="{{ route('acp.index', ['tab' => $tab ?? 'comment-manager']) }}" size="sm" variant="primary">Back</flux:button>
            @else
                <flux:button
                    onclick="if (document.referrer) { event.preventDefault(); window.history.back(); }"
                    href="{{ route('comments.index') }}"
                    wire:navigate
                    size="sm"
                    variant="primary"
                >
                    Back
                </flux:button>
            @endif
        </div>
    </div>
</x-layouts.app>
