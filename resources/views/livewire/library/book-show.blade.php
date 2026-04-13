<?php

use App\Actions\CheckDocumentVisibility;
use App\Services\DocumentationService;
use Livewire\Volt\Component;

new class extends Component {
    public string $book;
    public ?string $accessDenied = null;

    public function mount(string $book)
    {
        $this->book = $book;

        $service = app(DocumentationService::class);
        $bookData = $service->getBook($this->book);

        if (! $bookData) {
            abort(404);
        }

        try {
            CheckDocumentVisibility::run($bookData->visibility);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->accessDenied = $e->getMessage();
        }
    }

    public function getBookDataProperty()
    {
        return app(DocumentationService::class)->getBook($this->book);
    }

    public function getChildrenProperty(): array
    {
        if ($this->accessDenied) {
            return [];
        }

        return $this->bookData->parts->map(fn ($part) => [
            'title' => $part->title,
            'summary' => $part->summary,
            'url' => $part->url,
        ])->toArray();
    }

    public function getNavigationProperty()
    {
        return app(DocumentationService::class)->getBookNavigation($this->book);
    }

    public function getEditPathProperty(): ?string
    {
        $service = app(DocumentationService::class);

        return $service->getRelativePath($this->bookData->filePath);
    }

    public function getBreadcrumbsProperty(): array
    {
        return [];
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
                :title="$this->bookData->title"
                :summary="$this->bookData->summary"
                :body="$this->bookData->body"
                :children="$this->children"
                :breadcrumbs="$this->breadcrumbs"
                :navigation="$this->navigation"
                :currentUrl="url()->current()"
                :editPath="$this->editPath"
                :bookTitle="$this->bookData->title"
                childLabel="Parts"
            />
        @endif
    </div>
</section>
