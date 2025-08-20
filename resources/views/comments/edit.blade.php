<x-layouts.app>
    <div class="max-w-3xl mx-auto p-6">
        <flux:heading size="xl">Edit Comment</flux:heading>
        <div class="mt-2 text-xs text-gray-500" data-test="comment-content">{{ $comment->content }}</div>
        <div class="mt-4">
            <livewire:comments.edit :comment="$comment" />
        </div>
    </div>
</x-layouts.app>
