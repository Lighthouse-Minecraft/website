
<flux:card class="max-w-xl mx-auto mt-10">
    <div class="p-6 space-y-4">
        <flux:heading size="lg">Comment Details</flux:heading>
        <div class="text-base text-gray-200 mb-2">
            <strong>Content:</strong>
            <div class="mt-2">{!! $comment->content !!}</div>
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
        <div class="text-sm text-gray-400">
            <strong>Posted:</strong>
            {{ $comment->created_at->format('M d, Y H:i') }}
        </div>
        @if($comment->edited_at)
        <div class="text-xs text-gray-500">
            <strong>Edited:</strong> {{ $comment->edited_at->format('M d, Y H:i') }}
        </div>
        @endif
        <div class="w-full text-right mt-4">
            <flux:button wire:navigate href="{{ url()->previous() }}" variant="primary">Back</flux:button>
        </div>
    </div>
</flux:card>
