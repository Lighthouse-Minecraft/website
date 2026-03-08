@props(['items', 'depth' => 0])

<div class="{{ $depth > 0 ? 'ml-4 border-l border-zinc-200 dark:border-zinc-700 pl-3' : '' }}">
    @foreach($items as $item)
        <div class="py-1" wire:key="tree-{{ $item['path'] }}">
            @if($item['type'] === 'directory')
                <div class="flex items-center gap-2">
                    <flux:badge size="sm" color="zinc">{{ $item['title'] }}</flux:badge>
                    @if(collect($item['children'])->where('slug', '_index')->isEmpty())
                        <flux:text variant="subtle" class="text-xs">(no _index.md)</flux:text>
                    @endif
                </div>
                @if(!empty($item['children']))
                    <x-library.editor-tree :items="$item['children']" :depth="$depth + 1" />
                @endif
            @else
                <div class="flex items-center justify-between">
                    <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $item['title'] }}</span>
                    <flux:button size="xs" variant="ghost" icon="pencil"
                        href="{{ route('library.editor.edit', ['path' => $item['path']]) }}"
                        wire:navigate
                    />
                </div>
            @endif
        </div>
    @endforeach
</div>
