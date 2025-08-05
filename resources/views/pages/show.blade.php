<x-layouts.app>
    <div class="w-full space-y-6">
        <flux:heading size="xl">{{ $page->title }}</flux:heading>

        <div id="editor_content" class="prose max-w-none">
            {!! $page->content !!}
        </div>

        @can('update', $page)
            <div class="w-full text-right">
                <flux:button wire:navigate href="{{ route('admin.pages.edit', $page) }}" variant="primary">Edit Page</flux:button>
            </div>
        @endcan

    </div>
</x-layouts.app>
