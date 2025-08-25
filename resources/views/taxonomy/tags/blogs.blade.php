<x-layouts.app>
    <flux:header>Blogs with {{ $tag->name }}</flux:header>

    <ul>
        @foreach ($blogs as $blog)
            <li>{{ $blog->title }}</li>
        @endforeach
    </ul>

    {{ $blogs->links() }}
</x-layouts.app>
