<x-layouts.app>
    <flux:header>Blog Index</flux:header>
    <ul>
        @foreach($blogs as $blog)
            <li>{{ $blog->title }}</li>
        @endforeach
    </ul>
</x-layouts.app>
