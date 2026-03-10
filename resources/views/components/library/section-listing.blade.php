@props(['title', 'summary' => '', 'body' => '', 'children' => [], 'breadcrumbs' => [], 'childLabel' => 'Contents', 'navigation' => [], 'currentUrl' => '', 'editPath' => null, 'bookTitle' => ''])

@if($bookTitle)
<div class="mb-6 flex items-center justify-between">
    <flux:heading size="xl">{{ $bookTitle }}</flux:heading>
</div>
@endif

<div class="flex gap-6">
    {{-- Sidebar navigation (hidden on mobile, shown on lg+) --}}
    @if(count($navigation) > 0)
    <aside class="hidden lg:block w-80 shrink-0">
        <flux:card class="sticky top-4">
            <x-library.navigation :items="$navigation" :currentUrl="$currentUrl" />
        </flux:card>
    </aside>
    @endif

    <div class="flex-1 min-w-0">
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

    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ $title }}</flux:heading>
        @if($editPath && app()->isLocal())
            @can('edit-docs')
                <flux:button size="sm" variant="ghost" icon="pencil-square" href="{{ route('library.editor.edit', ['path' => $editPath]) }}" wire:navigate>
                    Edit Page
                </flux:button>
            @endcan
        @endif
    </div>
    @if($summary)
        <flux:text variant="subtle" class="mt-1">{{ $summary }}</flux:text>
    @endif

    @if($body)
        <flux:separator class="my-4" />
        <div class="prose dark:prose-invert max-w-none">
            {!! \Illuminate\Support\Str::markdown(\App\Services\Docs\PageDTO::processSiteUrls(\App\Services\Docs\PageDTO::processConfigVariables(\App\Services\Docs\PageDTO::processWikiLinks($body))), ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
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
    @elseif(empty(trim(strip_tags($body ?? ''))))
        <flux:separator class="my-4" />
        <flux:text variant="subtle">Content coming soon.</flux:text>
    @endif

    {{-- Report issue --}}
    @auth
        <flux:separator class="my-6" />
        <div class="flex justify-end">
            <flux:button size="sm" variant="subtle" icon="flag" x-on:click="$flux.modal('report-doc-issue').show()">
                Report Documentation Issue
            </flux:button>
        </div>
    @endauth
</flux:card>

    {{-- Report issue modal --}}
    @auth
        <livewire:library.report-doc-issue :pageTitle="$title" :pageUrl="$currentUrl" />
    @endauth
    </div>
</div>
