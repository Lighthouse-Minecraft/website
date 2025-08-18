<flux:card class="max-w-3xl mx-auto mt-10">
    <div class="p-8 space-y-6">
        <flux:heading size="xl" style="font-weight: bold; text-align: center;">Announcement Details</flux:heading>
        <div class="text-base text-gray-200 mb-2">
            <strong>Title:</strong>
            <div class="mt-2">{{ $announcement->title }}</div>
        </div>
        <br>
        <div class="text-base text-gray-200 mb-2">
            <strong>Content:</strong>
            <div class="mt-2 prose max-w-none">{!! $announcement->content !!}</div>
        </div>
        <br>
        <div class="text-sm text-gray-400">
            <strong>Author:</strong>
            @if($announcement->author)
                <flux:link href="{{ route('profile.show', ['user' => $announcement->author]) }}">
                    {{ $announcement->author->name }}
                </flux:link>
            @else
                <span class="text-gray-400">Unknown</span>
            @endif
        </div>
        <div class="text-sm text-gray-400">
            <strong>Posted:</strong>
            {{ $announcement->created_at->format('M d, Y H:i') }}
        </div>
        @if($announcement->updated_at && $announcement->updated_at != $announcement->created_at)
            <div class="text-xs text-gray-500">
                <strong>Edited:</strong> {{ $announcement->updated_at->format('M d, Y H:i') }}
            </div>
        @endif
        <div class="mb-4">
            <strong>Tags:</strong>
            @forelse($announcement->tags as $tag)
                <span class="inline-block bg-gray-200 text-gray-700 px-2 py-1 rounded mr-1">{{ $tag->name }}</span>
            @empty
                <span class="text-gray-400">No tags</span>
            @endforelse
        </div>
        <div class="mb-4">
            <strong>Categories:</strong>
            @forelse($announcement->categories as $category)
                <span class="inline-block bg-blue-200 text-blue-700 px-2 py-1 rounded mr-1">{{ $category->name }}</span>
            @empty
                <span class="text-gray-400">No categories</span>
            @endforelse
        </div>
        <div class="mt-6">
            <strong>Comments:</strong>
            <livewire:comments.comments-section :parent="$announcement" />
        </div>
        <div class="w-full text-right mt-4">
            @if(request('from') === 'acp')
                <flux:button wire:navigate href="{{ route('acp.index', ['tab' => 'announcement-manager']) }}" variant="primary">Back</flux:button>
            @else
                <flux:button wire:navigate href="{{ route('announcements.index') }}" variant="primary">Back</flux:button>
            @endif
        </div>
    </div>
</flux:card>
