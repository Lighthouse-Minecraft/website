<x-layouts.app>
    <flux:header>Tag Index</flux:header>
    <ul>
        @foreach($tags as $tag)
            <li>{{ $tag->name }}</li>
        @endforeach
    </ul>
</x-layouts.app>
