<?php

use App\Actions\CreateDocumentPage;
use App\Services\DocumentationService;
use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    #[Url]
    public string $type = 'page';

    #[Url]
    public string $parent = '';

    public string $filename = '';
    public string $title = '';
    public string $visibility = 'public';
    public int $order = 1;
    public string $summary = '';
    public string $body = '';

    public function mount()
    {
        $this->authorize('edit-docs');
    }

    public function getAvailableParentsProperty(): array
    {
        $service = app(DocumentationService::class);
        $tree = $service->getEditableTree();
        $parents = [];

        $this->flattenParents($tree, $parents, '');

        return $parents;
    }

    private function flattenParents(array $items, array &$parents, string $prefix): void
    {
        foreach ($items as $item) {
            if ($item['type'] === 'directory' || $item['type'] === 'section') {
                $path = $prefix ? $prefix . '/' . ($item['path'] ?? $item['slug']) : ($item['path'] ?? $item['slug']);
                $parents[] = ['label' => $item['title'], 'value' => $path];
                if (! empty($item['children'])) {
                    $this->flattenParents($item['children'], $parents, '');
                }
            }
        }
    }

    public function getIsSectionProperty(): bool
    {
        return $this->type === 'section';
    }

    public function create(): void
    {
        $this->authorize('edit-docs');

        $this->validate([
            'parent' => 'required|string',
            'filename' => 'required|string|max:255|regex:/^[a-z0-9-]+$/',
            'title' => 'required|string|max:255',
            'visibility' => 'required|in:public,users,resident,citizen,staff,officer',
            'order' => 'required|integer|min:1',
            'summary' => 'nullable|string|max:500',
            'body' => 'nullable|string',
        ]);

        if ($this->isSection) {
            $dirName = sprintf('%02d-%s', $this->order, $this->filename);
            $dirPath = $this->parent . '/' . $dirName;

            CreateDocumentPage::run($dirPath, '_index.md', [
                'title' => $this->title,
                'visibility' => $this->visibility,
                'order' => (int) $this->order,
                'summary' => $this->summary,
            ], $this->body ?? '');

            $path = $dirPath . '/_index.md';

            Flux::toast('Section created.', 'Created', variant: 'success');
        } else {
            $fullFilename = sprintf('%02d-%s.md', $this->order, $this->filename);

            $path = CreateDocumentPage::run($this->parent, $fullFilename, [
                'title' => $this->title,
                'visibility' => $this->visibility,
                'order' => (int) $this->order,
                'summary' => $this->summary,
            ], $this->body ?? '');

            Flux::toast('Document created.', 'Created', variant: 'success');
        }

        $this->redirect(route('library.editor.edit', ['path' => $path]), navigate: true);
    }
}; ?>

<section>
    <div class="mx-auto max-w-5xl p-6">
        <flux:card>
            <div class="flex items-center justify-between mb-2">
                <flux:heading size="xl">{{ $this->isSection ? 'Create New Section' : 'Create New Page' }}</flux:heading>
                <flux:button href="{{ route('library.editor.index') }}" variant="ghost" icon="arrow-left" wire:navigate>
                    Back
                </flux:button>
            </div>
            <flux:separator class="my-4" />

            <div class="grid gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>Type</flux:label>
                    <flux:select wire:model.live="type">
                        <flux:select.option value="page">Page</flux:select.option>
                        <flux:select.option value="section">Section (Part / Chapter)</flux:select.option>
                    </flux:select>
                    <flux:description>
                        @if($this->isSection)
                            Creates a new folder with an index page. Use this for parts and chapters.
                        @else
                            Creates a new page inside an existing section.
                        @endif
                    </flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Parent Directory</flux:label>
                    <flux:select wire:model="parent">
                        <flux:select.option value="">Select a location...</flux:select.option>
                        @foreach($this->availableParents as $p)
                            <flux:select.option value="{{ $p['value'] }}">{{ $p['label'] }} ({{ $p['value'] }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="parent" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ $this->isSection ? 'Folder Name (slug)' : 'Filename (slug)' }}</flux:label>
                    <flux:input wire:model="filename" placeholder="{{ $this->isSection ? 'my-section-name' : 'my-page-name' }}" />
                    <flux:description>Lowercase, hyphens only. Will be prefixed with order number.</flux:description>
                    <flux:error name="filename" />
                </flux:field>

                <flux:field>
                    <flux:label>Title</flux:label>
                    <flux:input wire:model="title" />
                    <flux:error name="title" />
                </flux:field>

                <flux:field>
                    <flux:label>Visibility</flux:label>
                    <flux:select wire:model="visibility">
                        <flux:select.option value="public">Public</flux:select.option>
                        <flux:select.option value="users">Users</flux:select.option>
                        <flux:select.option value="resident">Resident+</flux:select.option>
                        <flux:select.option value="citizen">Citizen</flux:select.option>
                        <flux:select.option value="staff">Staff</flux:select.option>
                        <flux:select.option value="officer">Officer+</flux:select.option>
                    </flux:select>
                    <flux:error name="visibility" />
                </flux:field>

                <flux:field>
                    <flux:label>Order</flux:label>
                    <flux:input type="number" wire:model="order" />
                    <flux:error name="order" />
                </flux:field>

                <flux:field class="md:col-span-2">
                    <flux:label>Summary</flux:label>
                    <flux:input wire:model="summary" />
                    <flux:error name="summary" />
                </flux:field>
            </div>

            <flux:field class="mt-4">
                <flux:label>Content (Markdown)</flux:label>
                <flux:textarea wire:model="body" rows="10" />
                <flux:error name="body" />
            </flux:field>

            <div class="mt-4">
                <flux:button variant="primary" wire:click="create">
                    {{ $this->isSection ? 'Create Section' : 'Create Page' }}
                </flux:button>
            </div>
        </flux:card>
    </div>
</section>
