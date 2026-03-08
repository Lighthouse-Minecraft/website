<?php

use App\Actions\CreateDocumentPage;
use App\Services\DocumentationService;
use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    #[Url]
    public string $type = 'book-page';

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

        $fullFilename = sprintf('%02d-%s.md', $this->order, $this->filename);

        $path = CreateDocumentPage::run($this->parent, $fullFilename, [
            'title' => $this->title,
            'visibility' => $this->visibility,
            'order' => (int) $this->order,
            'summary' => $this->summary,
        ], $this->body ?? '');

        Flux::toast('Document created.', 'Created', variant: 'success');

        $this->redirect(route('library.editor.edit', ['path' => $path]), navigate: true);
    }
}; ?>

<section>
    <div class="mx-auto max-w-5xl p-6">
        <flux:card>
            <div class="flex items-center justify-between mb-2">
                <flux:heading size="xl">Create New Document</flux:heading>
                <flux:button href="{{ route('library.editor.index') }}" variant="ghost" icon="arrow-left" wire:navigate>
                    Back
                </flux:button>
            </div>
            <flux:separator class="my-4" />

            <div class="grid gap-4 md:grid-cols-2">
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
                    <flux:label>Filename (slug)</flux:label>
                    <flux:input wire:model="filename" placeholder="my-page-name" />
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

                <flux:field>
                    <flux:label>Summary</flux:label>
                    <flux:input wire:model="summary" />
                    <flux:error name="summary" />
                </flux:field>
            </div>

            <flux:field class="mt-4">
                <flux:label>Content (Markdown)</flux:label>
                <flux:textarea wire:model="body" rows="15" />
                <flux:error name="body" />
            </flux:field>

            <div class="mt-4">
                <flux:button variant="primary" wire:click="create">Create Document</flux:button>
            </div>
        </flux:card>
    </div>
</section>
