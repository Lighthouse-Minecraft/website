@props(['title', 'summary' => '', 'body' => '', 'children' => [], 'breadcrumbs' => [], 'childLabel' => 'Contents'])

<flux:card>
    {{-- Breadcrumbs --}}
    @if(count($breadcrumbs) > 0)
    <nav class="mb-4 text-sm">
        @foreach($breadcrumbs as $i => $crumb)
            @if($i > 0) <span class="text-zinc-400 mx-1">/</span> @endif
            @if(!empty($crumb['url']) && $i < count($breadcrumbs) - 1)
                <flux:link href="{{ $crumb['url'] }}" wire:navigate>{{ $crumb['label'] }}</flux:link>
            @else
                <span class="text-zinc-500 dark:text-zinc-400">{{ $crumb['label'] }}</span>
            @endif
        @endforeach
    </nav>
    @endif

    <flux:heading size="xl">{{ $title }}</flux:heading>
    @if($summary)
        <flux:text variant="subtle" class="mt-1">{{ $summary }}</flux:text>
    @endif

    @if($body)
        <flux:separator class="my-4" />
        <div class="prose dark:prose-invert max-w-none">
            {!! \Illuminate\Support\Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
        </div>
    @endif

    @if(count($children) > 0)
        <flux:separator class="my-4" />
        <flux:heading size="md" class="mb-3">{{ $childLabel }}</flux:heading>
        <div class="space-y-3">
            @foreach($children as $child)
                <div wire:key="child-{{ $child['url'] }}">
                    <flux:link href="{{ $child['url'] }}" wire:navigate class="font-medium">
                        {{ $child['title'] }}
                    </flux:link>
                    @if(!empty($child['summary']))
                        <flux:text variant="subtle" class="text-sm">{{ $child['summary'] }}</flux:text>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <flux:separator class="my-4" />
        <flux:text variant="subtle">Content coming soon.</flux:text>
    @endif
</flux:card>
