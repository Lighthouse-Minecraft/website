<x-layouts.app>
    <h1>Blog Index</h1>
    <ul>
        @foreach($blogs as $blog)
            <li>{{ $blog->title }}</li>
        @endforeach
    </ul>
</x-layouts.app>
