<x-layouts.app.sidebar>
    <flux:main class="flex flex-col">
        <div class="flex-1">
            {{ $slot }}
        </div>
        @include('partials.footer')
    </flux:main>
</x-layouts.app.sidebar>
