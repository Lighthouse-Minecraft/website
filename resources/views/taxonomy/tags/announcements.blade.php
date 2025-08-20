<x-layouts.app>
    <flux:header>Announcements tagged {{ $tag->name }}</flux:header>

    <ul>
        @foreach ($announcements as $announcement)
            <li>{{ $announcement->title }}</li>
        @endforeach
    </ul>

    {{ $announcements->links() }}
</x-layouts.app>
