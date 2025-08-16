<x-layouts.app>
    <flux:header>Category Index</flux:header>
    <ul>
        @foreach($categories as $category)
            <li>{{ $category->name }}</li>
        @endforeach
    </ul>
</x-layouts.app>
