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
                        window.location = "{{ route('acp.index', ['tab' => $tab ?? 'announcement-manager']) }}";
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
                                window.location = "{{ route('announcements.index') }}";
                            @endif
                        }
                    }, 1200);
                </script>
            @endif
        @endisset
        <div class="bg-red-900/30 border-l-4 border-red-500 text-red-300 px-4 py-3 shadow-sm rounded">
            <strong class="font-semibold text-red-200">Warning:</strong>
            You are about to delete this announcement.<br>
            <span class="text-sm text-red-200">Use the buttons on the right-hand side to edit or confirm deletion, or use the back button to go back.</span>
        </div>

        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ $announcement->title }}</flux:heading>
            <div>
                @can('update', $announcement)
                    <flux:button href="{{ route('acp.announcements.edit', $announcement->id) }}" wire:navigate size="xs" icon="pencil-square"></flux:button>
                @endcan

                @can('delete', $announcement)
                    <form action="{{ route('acp.announcements.delete', $announcement->id) }}" method="POST" style="display:inline;">
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
            {!! $announcement->content !!}
        </div>

        <div class="mt-6 space-y-4">
            <div>
                <strong>Categories:</strong>
                @forelse($announcement->categories as $category)
                    <span class="inline-block bg-blue-200 text-blue-700 px-2 py-1 rounded mr-1">{{ $category->name }}</span>
                @empty
                    <span class="text-gray-400">No categories</span>
                @endforelse
            </div>

            <div>
                <strong>Tags:</strong>
                @forelse($announcement->tags as $tag)
                    <span class="inline-block bg-gray-200 text-gray-700 px-2 py-1 rounded mr-1">{{ $tag->name }}</span>
                @empty
                    <span class="text-gray-400">No tags</span>
                @endforelse
            </div>

            <div class="text-sm text-gray-500 flex items-center gap-2">
                @php $author = $announcement->author; @endphp
                <span>Published by</span>
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
                <span>on <time datetime="{{ $announcement->created_at->toIso8601String() }}">{{ $announcement->created_at->format('m/d/y @ h:i a') }}</time></span>
            </div>

            <div>
                <strong>Comments:</strong>
                <ul>
                    @forelse($announcement->comments as $comment)
                        <li class="mb-2 border-b pb-2">
                            <div class="text-xs text-gray-500">
                                By
                                @if($comment->author)
                                    <flux:link href="{{ route('profile.show', ['user' => $comment->author]) }}">
                                        {{ $comment->author->name }}
                                    </flux:link>
                                @else
                                    Unknown
                                @endif
                                on <time datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->format('M d, Y H:i') }}</time>
                            </div>
                            <br>
                            <div class="prose max-w-none whitespace-pre-wrap break-words [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:max-w-full [&_pre]:w-full [&_pre]:overflow-x-auto [&_code]:break-words [&_code]:break-all">
                                {!! $comment->content !!}
                            </div>
                            <br>
                            @can('delete', $comment)
                                <form method="POST" action="{{ route('comments.destroy', $comment->id) }}" style="display:inline; margin-top:6px;">
                                    @csrf
                                    @method('DELETE')
                                    <flux:button type="submit" size="xs" icon="trash" variant="danger">Delete</flux:button>
                                </form>
                            @endcan
                        </li>
                    @empty
                        <li class="text-gray-400">No comments yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="flex items-center gap-3">
            @if(request('from') === 'acp' || request()->routeIs('acp.*'))
                <flux:button wire:navigate href="{{ route('acp.index', ['tab' => $tab ?? 'announcement-manager']) }}" size="sm" variant="primary">Back</flux:button>
            @else
                <flux:button
                    onclick="if (document.referrer) { event.preventDefault(); window.history.back(); }"
                    href="{{ route('announcements.index') }}"
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
