<x-layouts.app.sidebar>
    <flux:main>
        {{ $slot }}
        @include('partials.footer')
    </flux:main>
</x-layouts.app.sidebar>
