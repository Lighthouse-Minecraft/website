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

    public function getPreviewHtmlProperty(): string
    {
        return \Illuminate\Support\Str::markdown($this->body ?: '', [
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
                <flux:button href="{{ route('library.editor.index') }}" variant="ghost" icon="arrow-left" wire:navigate>
                    Back
                </flux:button>
            </div>
            <flux:text variant="subtle">{{ $path }}</flux:text>
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
    </div>
</section>
