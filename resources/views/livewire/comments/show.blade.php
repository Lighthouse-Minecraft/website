<flux:card class="max-w-3xl mx-auto mt-10">
    <div class="p-6 space-y-4">
        <flux:heading size="xl" style="font-weight: bold; text-align: center;">Comment Details</flux:heading>

        {{-- Parent preview section --}}
        @php
            $rawType = (string) ($comment->getRawOriginal('commentable_type') ?? '');
            $normalized = strtolower(class_basename($rawType));
            if (! in_array($normalized, ['blog', 'announcement'], true)) {
                // Already stored aliases like 'blog'/'announcement' or other strings
                $normalized = strtolower($rawType);
            }

            $parent = null;
            if ($normalized === 'blog') {
                $parent = \App\Models\Blog::withTrashed()->find($comment->commentable_id);
            } elseif ($normalized === 'announcement') {
                $parent = \App\Models\Announcement::withTrashed()->find($comment->commentable_id);
            }

            $parentType = $parent ? $normalized : null;
            $parentTitle = $parent->title ?? ($comment->commentable_title ?? '');
            $parentContent = $parent->content ?? ($comment->commentable_content ?? '');
            $parentRoute = '#';
            if ($parent && $normalized === 'blog') {
                $parentRoute = route('blogs.show', $parent->id);
            } elseif ($parent && $normalized === 'announcement') {
                $parentRoute = route('announcements.show', $parent->id);
            }
        @endphp

        <div class="mb-6 p-4 rounded bg-gray-900/40 border border-gray-700 flex items-center justify-between">
            <div class="flex-1">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="text-xs uppercase tracking-wide text-gray-400">{{ $parentType }}</span>
                        <div class="font-semibold text-lg text-gray-100">{{ $parentTitle }}</div>
                    </div>

                    @php $isDeleted = $parent ? (method_exists($parent, 'trashed') && $parent->trashed()) : true; @endphp

                    @if($isDeleted)
                        <span class="inline-block h-fit px-1 py-0.5 text-[10px] font-semibold bg-red-700 text-white rounded align-middle">Deleted</span>
                    @endif
                </div>

                @if($parentContent)
                    <div class="prose max-w-none mt-2 whitespace-pre-wrap break-words [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:max-w-full [&_pre]:w-full [&_pre]:overflow-x-auto [&_code]:break-words">{!! $parentContent !!}</div>
                @endif
            </div>

            @if($parent && ! $isDeleted)
                <flux:button wire:navigate href="{{ $parentRoute }}" icon="eye" variant="primary">View</flux:button>
            @endif
        </div>

        {{-- Comment preview section --}}
        <div class="text-base text-gray-200 mb-2">
            <strong>Comment:</strong>

            <div class="mt-2 prose max-w-none whitespace-pre-wrap break-words [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:max-w-full [&_pre]:w-full [&_pre]:overflow-x-auto [&_code]:break-words">
                {!! $comment->content !!}
            </div>
        </div>

        <div class="text-sm text-gray-400">
            <strong>Author:</strong>

            @if($comment->author)
                <flux:link href="{{ route('profile.show', ['user' => $comment->author]) }}">
                    {{ $comment->author->name }}
                </flux:link>
            @else
                <span class="text-gray-400">Unknown</span>
            @endif
        </div>

        @php
            $createdAt = $comment->created_at instanceof \Carbon\CarbonInterface
                ? $comment->created_at
                : (\Illuminate\Support\Carbon::parse($comment->created_at));
        @endphp
        <div class="text-sm text-gray-400">
            <strong>Posted:</strong>
            <time class="comment-ts" datetime="{{ optional($createdAt)->toIso8601String() }}">{{ optional($createdAt)->format('M d, Y H:i') }}</time>
        </div>

        @php
            $editedAt = $comment->edited_at instanceof \Carbon\CarbonInterface
                ? $comment->edited_at
                : ($comment->edited_at ? \Illuminate\Support\Carbon::parse($comment->edited_at) : null);
        @endphp
        @if($editedAt)
            <div class="text-xs text-gray-500">
                <strong>Edited:</strong> <time class="comment-ts" datetime="{{ $editedAt->toIso8601String() }}">{{ $editedAt->format('M d, Y H:i') }}</time>
            </div>
        @endif

        @can('delete', $comment)
            <div class="w-full text-right mt-4">
                <flux:button
                    wire:navigate
                    href="{{ route('acp.comments.confirmDelete', ['id' => $comment->id]) }}"
                    size="xs"
                    icon="trash"
                    variant="danger"
                >Delete</flux:button>
            </div>
        @endcan

        <div class="w-full text-right mt-4">
            @if(request('from') === 'acp' || request()->routeIs('acp.*'))
                <flux:button wire:navigate href="{{ route('acp.index', ['tab' => 'comment-manager']) }}" variant="primary">Back</flux:button>
            @else
                <flux:button
                    onclick="if (document.referrer) { event.preventDefault(); window.history.back(); }"
                    href="{{ request('from') === 'dashboard' ? route('dashboard') : route('comments.index') }}"
                    wire:navigate
                    variant="primary"
                >
                    Back
                </flux:button>
            @endif
        </div>
    </div>
</flux:card>
