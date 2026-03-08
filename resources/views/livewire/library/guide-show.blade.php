<?php

use App\Actions\CheckDocumentVisibility;
use App\Services\DocumentationService;
use Livewire\Volt\Component;

new class extends Component {
    public string $guide;
    public ?string $accessDenied = null;

    public function mount(string $guide)
    {
        $this->guide = $guide;

        $service = app(DocumentationService::class);
        $guideData = $service->getGuide($this->guide);

        if (! $guideData) {
            abort(404);
        }

        try {
            CheckDocumentVisibility::run($guideData->visibility);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->accessDenied = $e->getMessage();
        }
    }

    public function getGuideDataProperty()
    {
        return app(DocumentationService::class)->getGuide($this->guide);
    }

    public function getChildrenProperty(): array
    {
        if ($this->accessDenied) {
            return [];
        }

        return $this->guideData->pages->map(fn ($page) => [
            'title' => $page->title,
            'summary' => $page->summary,
            'url' => $page->url,
        ])->toArray();
    }

    public function getBreadcrumbsProperty(): array
    {
        return [
            ['label' => 'Guides', 'url' => route('library.guides.index')],
            ['label' => $this->guideData->title, 'url' => null],
        ];
    }
}; ?>

<section>
    <div class="mx-auto max-w-4xl p-6">
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
                :title="$this->guideData->title"
                :summary="$this->guideData->summary"
                :body="$this->guideData->body"
                :children="$this->children"
                :breadcrumbs="$this->breadcrumbs"
                childLabel="Pages"
            />
        @endif
    </div>
</section>
