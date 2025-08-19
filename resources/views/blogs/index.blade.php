<x-layouts.app>
    <flux:header class="text-2xl font-bold">Blog Index</flux:header>
    <div class="space-y-4">
        @foreach($blogs as $blog)
            <div class="bg-zinc-800 border border-blue-900 rounded-lg p-4 flex items-center justify-between">
                <div>
                    <div class="text-white font-semibold">{{ $blog->title }}</div>
                    <div class="text-blue-300 text-sm whitespace-pre-wrap break-words [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:max-w-full [&_pre]:w-full [&_pre]:overflow-x-auto [&_code]:break-words [&_code]:break-all">{!! Str::limit($blog->content, 120) !!}</div>
                </div>
                <flux:button size="xs" wire:navigate href="{{ route('blogs.show', ['id' => $blog->id, 'from' => 'index']) }}" variant="primary">Read Full Blog</flux:button>
            </div>
        @endforeach
        <div class="mt-4">{{ $blogs->links() }}</div>
    </div>
</x-layouts.app>
