<?php

use App\Services\DocumentationService;
use Livewire\Volt\Component;

new class extends Component {
    public function mount()
    {
        $this->authorize('edit-docs');
    }

    public function getTreeProperty()
    {
        return app(DocumentationService::class)->getEditableTree();
    }
}; ?>

<section>
    <div class="mx-auto max-w-4xl p-6">
        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="xl">Documentation Editor</flux:heading>
                <flux:button href="{{ route('library.editor.create') }}" icon="plus" wire:navigate>
                    New Page
                </flux:button>
            </div>
            <flux:separator class="my-4" />

            @forelse($this->tree as $section)
                <div class="mb-6" wire:key="section-{{ $section['slug'] }}">
                    <flux:heading size="lg" class="mb-2">{{ $section['title'] }}</flux:heading>
                    <x-library.editor-tree :items="$section['children']" />
                </div>
            @empty
                <flux:text variant="subtle">No documentation files found. Create your first page to get started.</flux:text>
            @endforelse
        </flux:card>
    </div>
</section>
