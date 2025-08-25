<x-layouts.app>
    <flux:header class="text-2xl font-bold">Announcement Index</flux:header>
    <div class="space-y-4">
        @foreach($announcements as $announcement)
            <div class="bg-zinc-800 border border-purple-900 rounded-lg p-4 flex items-center justify-between">
                <div>
                    <div class="text-white font-semibold">{{ $announcement->title }}</div>
                    <div class="text-purple-300 text-sm whitespace-pre-wrap break-words [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:max-w-full [&_pre]:w-full [&_pre]:overflow-x-auto [&_code]:break-words [&_code]:break-all">{!! Str::limit($announcement->content, 120) !!}</div>
                </div>
                <flux:button size="xs" wire:navigate href="{{ route('announcements.show', ['id' => $announcement->id, 'from' => 'index']) }}" variant="primary">Read Full Announcement</flux:button>
            </div>
        @endforeach
        <div class="mt-4">{{ $announcements->links() }}</div>
    </div>
</x-layouts.app>
