<?php

use App\Actions\CheckDocumentVisibility;
use App\Services\DocumentationService;
use Livewire\Volt\Component;

new class extends Component {
    public string $book;
    public string $part;
    public ?string $accessDenied = null;

    public function mount(string $book, string $part)
    {
        $this->book = $book;
        $this->part = $part;

        $service = app(DocumentationService::class);
        $partData = $service->findPartIndex($this->book, $this->part);

        if (! $partData) {
            abort(404);
        }

        try {
            CheckDocumentVisibility::run($partData->visibility);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->accessDenied = $e->getMessage();
        }
    }

    public function getPartDataProperty()
    {
        return app(DocumentationService::class)->findPartIndex($this->book, $this->part);
    }

    public function getChildrenProperty(): array
    {
        if ($this->accessDenied) {
            return [];
        }

        return $this->partData->chapters->map(fn ($chapter) => [
            'title' => $chapter->title,
            'summary' => $chapter->summary,
            'url' => $chapter->url,
        ])->toArray();
    }

    public function getNavigationProperty()
    {
        return app(DocumentationService::class)->getBookNavigation($this->book);
    }

    public function getEditPathProperty(): ?string
    {
        return app(DocumentationService::class)->getRelativePath($this->partData->filePath);
    }

    public function getBreadcrumbsProperty(): array
    {
        $service = app(DocumentationService::class);
        $book = $service->getBook($this->book);

        return array_filter([
            ['label' => 'Handbooks', 'url' => route('library.books.index')],
            $book ? ['label' => $book->title, 'url' => $book->url] : null,
            ['label' => $this->partData->title, 'url' => null],
        ]);
    }
}; ?>

<section>
    <div class="mx-auto max-w-6xl p-6">
        @if($accessDenied === 'login_required')
            <flux:card>
                <flux:heading size="lg">Login Required</flux:heading>
                <flux:text class="mt-2">You need to log in to view this content.</flux:text>
                <flux:button variant="primary" href="{{ route('login') }}" wire:navigate class="mt-4">Log In</flux:button>
            </flux:card>
        @elseif($accessDenied)
            <flux:card>
                <flux:heading size="lg">Access Restricted</flux:heading>
                <flux:text class="mt-2">This content is available to staff members only.</flux:text>
            </flux:card>
        @else
            <x-library.section-listing
                :title="$this->partData->title"
                :summary="$this->partData->summary"
                :body="$this->partData->body"
                :children="$this->children"
                :breadcrumbs="$this->breadcrumbs"
                :navigation="$this->navigation"
                :currentUrl="url()->current()"
                :editPath="$this->editPath"
                childLabel="Chapters"
            />
        @endif
    </div>
</section>
