@props(['items', 'currentUrl' => ''])

<flux:navlist variant="outline">
    @foreach($items as $item)
        @if(isset($item['children']) && count($item['children']) > 0)
            <flux:navlist.group :heading="$item['label']" class="grid">
                @foreach($item['children'] as $child)
                    @if(isset($child['children']) && count($child['children']) > 0)
                        {{-- Chapter level with pages — expand only when viewing a page in this chapter --}}
                        @php
                            $chapterIsActive = $currentUrl === $child['url'] || str_starts_with($currentUrl, $child['url'] . '/');
                        @endphp
                        <flux:navlist.group :heading="$child['label']" expandable :expanded="$chapterIsActive" class="grid ml-2">
                            @foreach($child['children'] as $page)
                                <flux:navlist.item
                                    :href="$page['url']"
                                    :current="$currentUrl === $page['url']"
                                    wire:navigate
                                >
                                    {{ $page['label'] }}
                                </flux:navlist.item>
                            @endforeach
                        </flux:navlist.group>
                    @else
                        <flux:navlist.item
                            :href="$child['url']"
                            :current="$currentUrl === $child['url']"
                            wire:navigate
                        >
                            {{ $child['label'] }}
                        </flux:navlist.item>
                    @endif
                @endforeach
            </flux:navlist.group>
        @else
            <flux:navlist.item
                :href="$item['url']"
                :current="$currentUrl === $item['url']"
                wire:navigate
            >
                {{ $item['label'] }}
            </flux:navlist.item>
        @endif
    @endforeach
</flux:navlist>
