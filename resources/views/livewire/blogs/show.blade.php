
<flux:card class="max-w-3xl mx-auto mt-10">
    <div class="p-8 space-y-6">
        <flux:heading size="xl" style="font-weight: bold; text-align: center;">Blog Details</flux:heading>
        <div class="mb-2 bg-transparent">
            <h2 class="text-lg font-semibold text-gray-100 text-center">{{ $blog->title }}</h2>
        </div>
        <div class="text-base text-gray-200 mb-2 bg-transparent">
            <div class="mt-2 prose max-w-none whitespace-pre-wrap break-words [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:max-w-full [&_pre]:w-full [&_pre]:overflow-x-auto [&_code]:break-words [&_code]:break-all" style="text-align: justify;">
                {!! $blog->content !!}
            </div>
        </div>
        <br>
        <div class="text-sm text-gray-400">
            <strong>Author:</strong>
            @if($blog->author)
                <flux:link href="{{ route('profile.show', ['user' => $blog->author]) }}">
                    {{ $blog->author->name }}
                </flux:link>
            @else
                <span class="text-gray-400">Unknown</span>
            @endif
        </div>
        <div class="text-sm text-gray-400">
            <strong>Posted:</strong>
            <time data-localize datetime="{{ $blog->created_at->toIso8601String() }}">{{ $blog->created_at->format('M d, Y H:i') }}</time>
        </div>
        @if($blog->updated_at && $blog->updated_at != $blog->created_at)
            <div class="text-xs text-gray-500">
                <strong>Edited:</strong> <time data-localize datetime="{{ $blog->updated_at->toIso8601String() }}">{{ $blog->updated_at->format('M d, Y H:i') }}</time>
            </div>
        @endif
        <div class="mb-4">
            <strong>Categories:</strong>
            @forelse($blog->categories as $category)
                <span class="text-sm inline-block bg-blue-200 text-blue-700 px-2 py-1 rounded mr-1">{{ $category->name }}</span>
            @empty
                <span class="text-sm text-gray-400">No categories</span>
            @endforelse
        </div>
        <div class="mb-4">
            <strong>Tags:</strong>
            @forelse($blog->tags as $tag)
                <span class="text-sm inline-block bg-gray-200 text-gray-700 px-2 py-1 rounded mr-1">{{ $tag->name }}</span>
            @empty
                <span class="text-sm text-gray-400">No tags</span>
            @endforelse
        </div>
        <div class="mt-6">
            <strong>Comments:</strong>
            <livewire:comments.comments-section :parent="$blog" />
        </div>
        <div class="w-full text-right mt-4">
            @if(request('from') === 'acp' || request()->routeIs('acp.*'))
                <flux:button wire:navigate href="{{ route('acp.index', ['tab' => 'blog-manager']) }}" variant="primary">Back</flux:button>
            @else
                <flux:button
                    onclick="if (document.referrer) { event.preventDefault(); window.history.back(); }"
                    href="{{ request('from') === 'dashboard' ? route('dashboard') : route('blogs.index') }}"
                    wire:navigate
                    variant="primary"
                >
                    Back
                </flux:button>
            @endif
        </div>
    </div>
</flux:card>
