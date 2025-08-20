<x-layouts.app>
    <flux:header>Blogs in {{ $category->name }}</flux:header>

    <ul>
        @foreach ($blogs as $blog)
            <li>{{ $blog->title }}</li>
        @endforeach
    </ul>

    {{ $blogs->links() }}
</x-layouts.app>
