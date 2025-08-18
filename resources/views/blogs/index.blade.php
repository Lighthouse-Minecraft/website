<x-layouts.app>
    <flux:header class="text-2xl font-bold">Blog Index</flux:header>
    <div class="space-y-4">
        @foreach($blogs as $blog)
            <div class="bg-zinc-800 border border-blue-900 rounded-lg p-4 flex items-center justify-between">
                <div>
                    <div class="text-white font-semibold">{{ $blog->title }}</div>
                    <div class="text-blue-300 text-sm">{!! Str::limit($blog->content, 120) !!}</div>
                </div>
                <a href="{{ route('blogs.show', ['id' => $blog->id, 'from' => 'index']) }}" class="bg-zinc-700 text-white px-4 py-2 rounded hover:bg-zinc-600 transition">Read Full Blog</a>
            </div>
        @endforeach
        <div class="mt-4">{{ $blogs->links() }}</div>
    </div>
</x-layouts.app>
