<?php

use App\Actions\SaveDocumentPage;
use App\Services\DocumentationService;
use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    #[Url]
    public string $path = '';

    public string $title = '';
    public string $visibility = 'public';
    public int $order = 1;
    public string $summary = '';
    public string $body = '';
    public bool $showPreview = false;

    public string $newSlug = '';

    public function mount()
    {
        $this->authorize('edit-docs');

        if ($this->path) {
            $service = app(DocumentationService::class);
            $fullPath = resource_path('library/' . $this->path);
            $parsed = $service->parseFile($fullPath);

            $this->title = $parsed['meta']['title'] ?? '';
            $this->visibility = $parsed['meta']['visibility'] ?? 'public';
            $this->order = $parsed['meta']['order'] ?? 1;
            $this->summary = $parsed['meta']['summary'] ?? '';
            $this->body = $parsed['body'];
        }
    }

    public function save(): void
    {
        $this->authorize('edit-docs');

        $this->validate([
            'title' => 'required|string|max:255',
            'visibility' => 'required|in:public,users,resident,citizen,staff,officer',
            'order' => 'required|integer|min:1',
            'summary' => 'nullable|string|max:500',
            'body' => 'nullable|string',
        ]);

        SaveDocumentPage::run($this->path, [
            'title' => $this->title,
            'visibility' => $this->visibility,
            'order' => (int) $this->order,
            'summary' => $this->summary,
        ], $this->body ?? '');

        Flux::toast('Document saved.', 'Saved', variant: 'success');
    }

    public function rename(): void
    {
        $this->authorize('edit-docs');

        $this->validate([
            'newSlug' => 'required|string|max:255|regex:/^[a-z0-9-]+$/',
        ], [
            'newSlug.regex' => 'The slug must contain only lowercase letters, numbers, and hyphens.',
        ]);

        $filename = basename($this->path);
        $isIndex = $filename === '_index.md';

        if ($isIndex) {
            Flux::toast('Cannot rename index files. Rename the directory instead.', 'Error', variant: 'danger');
            return;
        }

        // Preserve the numeric order prefix from the current filename
        $currentFilename = basename($this->path);
        if (preg_match('/^(\d+-)/', $currentFilename, $matches)) {
            $newFilename = $matches[1] . $this->newSlug . '.md';
        } else {
            $newFilename = $this->newSlug . '.md';
        }

        $service = app(DocumentationService::class);

        try {
            $newPath = $service->renamePage($this->path, $newFilename);
        } catch (\InvalidArgumentException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');
            return;
        }

        Flux::modal('rename-file-modal')->close();
        Flux::toast('File renamed successfully.', 'Renamed', variant: 'success');

        $this->redirect(route('library.editor.edit', ['path' => $newPath]), navigate: true);
    }

    public function getCurrentSlugProperty(): string
    {
        $filename = basename($this->path);
        $slug = preg_replace('/^\d+-/', '', $filename);

        return str_replace('.md', '', $slug);
    }

    public function getIsIndexFileProperty(): bool
    {
        return basename($this->path) === '_index.md';
    }

    public function getViewUrlProperty(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return app(DocumentationService::class)->resolveViewUrl($this->path);
    }

    public function getPreviewHtmlProperty(): string
    {
        $body = \App\Services\Docs\PageDTO::processConfigVariables(
            \App\Services\Docs\PageDTO::processWikiLinks($this->body ?: '')
        );

        return \Illuminate\Support\Str::markdown($body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}; ?>

<section>
    <div class="mx-auto max-w-5xl p-6">
        <flux:card>
            <div class="flex items-center justify-between mb-2">
                <flux:heading size="xl">Edit Document</flux:heading>
                <div class="flex items-center gap-2">
                    @if($this->viewUrl)
                        <flux:button href="{{ $this->viewUrl }}" variant="ghost" icon="eye" wire:navigate>
                            View Page
                        </flux:button>
                    @endif
                    <flux:button href="{{ route('library.editor.index') }}" variant="ghost" icon="arrow-left" wire:navigate>
                        Back
                    </flux:button>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <flux:text variant="subtle">{{ $path }}</flux:text>
                @unless($this->isIndexFile)
                    <flux:button size="xs" variant="ghost" icon="pencil-square" x-on:click="$wire.set('newSlug', '{{ $this->currentSlug }}'); $flux.modal('rename-file-modal').show()">
                        Rename
                    </flux:button>
                @endunless
            </div>
            <flux:separator class="my-4" />

            <div class="grid gap-4 md:grid-cols-2">
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
                <flux:textarea wire:model.live.debounce.500ms="body" rows="20" />
                <flux:error name="body" />
            </flux:field>

            <div class="mt-4 flex items-center gap-4">
                <flux:button variant="primary" wire:click="save">Save</flux:button>
                <flux:button variant="ghost" wire:click="$toggle('showPreview')">
                    {{ $showPreview ? 'Hide Preview' : 'Show Preview' }}
                </flux:button>
            </div>

            @if($showPreview)
                <flux:separator class="my-4" />
                <flux:heading size="md">Preview</flux:heading>
                <div class="prose dark:prose-invert max-w-none mt-2">
                    {!! $this->previewHtml !!}
                </div>
            @endif
        </flux:card>

        {{-- Rename modal --}}
        <flux:modal name="rename-file-modal" class="md:w-96">
            <div class="space-y-4">
                <flux:heading size="lg">Rename File</flux:heading>
                <flux:text variant="subtle">
                    Change the filename slug. The order prefix and <code>.md</code> extension are preserved automatically.
                </flux:text>

                <flux:field>
                    <flux:label>Current Slug</flux:label>
                    <flux:input :value="$this->currentSlug" disabled />
                </flux:field>

                <flux:field>
                    <flux:label>New Slug</flux:label>
                    <flux:input wire:model="newSlug" placeholder="my-new-slug" />
                    <flux:description>Lowercase letters, numbers, and hyphens only.</flux:description>
                    <flux:error name="newSlug" />
                </flux:field>

                <div class="flex gap-2 justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" wire:click="rename">Rename</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</section>
