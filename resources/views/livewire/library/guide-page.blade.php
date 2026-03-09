<?php

use App\Actions\CheckDocumentVisibility;
use App\Services\DocumentationService;
use Livewire\Volt\Component;

new class extends Component {
    public string $guide;
    public string $page;
    public ?string $accessDenied = null;
    public string $resolvedTitle = '';

    public function mount(string $guide, string $page)
    {
        $this->guide = $guide;
        $this->page = $page;

        $service = app(DocumentationService::class);
        $pageData = $service->findGuidePage($this->guide, $this->page);

        if (! $pageData) {
            abort(404);
        }

        $this->resolvedTitle = $pageData->title;

        try {
            CheckDocumentVisibility::run($pageData->visibility);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->accessDenied = $e->getMessage();
        }
    }

    public function getPageDataProperty()
    {
        return app(DocumentationService::class)->findGuidePage($this->guide, $this->page);
    }

    public function getNavigationProperty()
    {
        return app(DocumentationService::class)->getGuideNavigation($this->guide);
    }

    public function getBreadcrumbsProperty()
    {
        $service = app(DocumentationService::class);
        $guide = $service->getGuide($this->guide);

        return array_filter([
            ['label' => 'Guides', 'url' => route('library.guides.index')],
            $guide ? ['label' => $guide->title, 'url' => $guide->url] : null,
            ['label' => $this->resolvedTitle, 'url' => null],
        ]);
    }

    public function getEditPathProperty(): ?string
    {
        return app(DocumentationService::class)->getRelativePath($this->pageData->filePath);
    }

    public function getAdjacentProperty()
    {
        return app(DocumentationService::class)->getAdjacentPages('guide', $this->guide);
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
            <x-library.reader
                :title="$this->pageData->title"
                :html="$this->pageData->renderedHtml()"
                :breadcrumbs="$this->breadcrumbs"
                :navigation="$this->navigation"
                :prev="$this->adjacent['prev'] ?? null"
                :next="$this->adjacent['next'] ?? null"
                :currentUrl="url()->current()"
                :editPath="$this->editPath"
            />
        @endif
    </div>
</section>
