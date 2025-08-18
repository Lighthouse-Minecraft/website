<x-layouts.app>
    <flux:header class="text-2xl font-bold">Announcement Index</flux:header>
    <div class="space-y-4">
        @foreach($announcements as $announcement)
            <div class="bg-zinc-800 border border-purple-900 rounded-lg p-4 flex items-center justify-between">
                <div>
                    <div class="text-white font-semibold">{{ $announcement->title }}</div>
                    <div class="text-purple-300 text-sm">{!! Str::limit($announcement->content, 120) !!}</div>
                </div>
                <a href="{{ route('announcements.show', ['id' => $announcement->id, 'from' => 'index']) }}" class="bg-zinc-700 text-white px-4 py-2 rounded hover:bg-zinc-600 transition">Read Full Announcement</a>
            </div>
        @endforeach
        <div class="mt-4">{{ $announcements->links() }}</div>
    </div>
</x-layouts.app>
