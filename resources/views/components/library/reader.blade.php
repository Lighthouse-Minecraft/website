@props(['title', 'html', 'breadcrumbs' => [], 'navigation' => [], 'prev' => null, 'next' => null, 'currentUrl' => '', 'editPath' => null])

<div class="flex gap-6">
    {{-- Sidebar navigation (hidden on mobile, shown on lg+) --}}
    @if(count($navigation) > 0)
    <aside class="hidden lg:block w-80 shrink-0">
        <flux:card class="sticky top-4">
            <x-library.navigation :items="$navigation" :currentUrl="$currentUrl" />
        </flux:card>
    </aside>
    @endif

    {{-- Main content --}}
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
            <flux:separator class="my-4" />

            {{-- Rendered markdown --}}
            <div class="prose dark:prose-invert max-w-none">
                {!! $html !!}
            </div>

            {{-- Prev/Next navigation --}}
            @if($prev || $next)
            <flux:separator class="my-6" />
            <div class="flex justify-between">
                @if($prev)
                    <flux:button variant="ghost" icon="arrow-left" href="{{ $prev->url }}" wire:navigate>
                        {{ $prev->title }}
                    </flux:button>
                @else
                    <div></div>
                @endif
                @if($next)
                    <flux:button variant="ghost" icon-trailing="arrow-right" href="{{ $next->url }}" wire:navigate>
                        {{ $next->title }}
                    </flux:button>
                @endif
            </div>
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
