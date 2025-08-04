<x-layouts.app>
    <div class="w-full space-y-6">
        <flux:heading size="xl">{{ $page->title }}</flux:heading>

        <div class="prose max-w-full space-y-4">{!! $page->content !!}</div>
    </div>
</x-layouts.app>
