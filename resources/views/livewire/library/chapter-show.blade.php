<?php

use App\Actions\CheckDocumentVisibility;
use App\Services\DocumentationService;
use Livewire\Volt\Component;

new class extends Component {
    public string $book;
    public string $part;
    public string $chapter;
    public ?string $accessDenied = null;

    public function mount(string $book, string $part, string $chapter)
    {
        $this->book = $book;
        $this->part = $part;
        $this->chapter = $chapter;

        $service = app(DocumentationService::class);
        $chapterData = $service->findChapterIndex($this->book, $this->part, $this->chapter);

        if (! $chapterData) {
            abort(404);
        }

        try {
            CheckDocumentVisibility::run($chapterData->visibility);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->accessDenied = $e->getMessage();
        }
    }

    public function getChapterDataProperty()
    {
        return app(DocumentationService::class)->findChapterIndex($this->book, $this->part, $this->chapter);
    }

    public function getChildrenProperty(): array
    {
        if ($this->accessDenied) {
            return [];
        }

        return $this->chapterData->pages->map(fn ($page) => [
            'title' => $page->title,
            'summary' => $page->summary,
            'url' => $page->url,
        ])->toArray();
    }

    public function getNavigationProperty()
    {
        return app(DocumentationService::class)->getBookNavigation($this->book);
    }

    public function getEditPathProperty(): ?string
    {
        return app(DocumentationService::class)->getRelativePath($this->chapterData->filePath);
    }

    public function getBookTitleProperty(): string
    {
        return app(DocumentationService::class)->getBook($this->book)?->title ?? '';
    }

    public function getBreadcrumbsProperty(): array
    {
        $service = app(DocumentationService::class);
        $part = $service->findPartIndex($this->book, $this->part);

        return array_filter([
            $part ? ['label' => $part->title, 'url' => $part->url] : null,
            ['label' => $this->chapterData->title, 'url' => null],
        ]);
    }
}; ?>

<section>
    <div class="mx-auto max-w-6xl p-2 sm:p-6">
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
                :title="$this->chapterData->title"
                :summary="$this->chapterData->summary"
                :body="$this->chapterData->body"
                :children="$this->children"
                :breadcrumbs="$this->breadcrumbs"
                :navigation="$this->navigation"
                :currentUrl="url()->current()"
                :editPath="$this->editPath"
                :bookTitle="$this->bookTitle"
                :lastUpdated="$this->chapterData->lastUpdated"
                childLabel="Pages"
            />
        @endif
    </div>
</section>
