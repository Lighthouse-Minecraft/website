<?php

use Livewire\Volt\Component;
use App\Models\Page;
use Flux\Flux;

new class extends Component {
    public Page $page;
    public $pageTitle = '';
    public $pageSlug = '';
    public $pageContent = '';
    public $isPublished = false;

    public function mount(Page $page)
    {
        $this->page = $page;
        $this->pageTitle = $page->title;
        $this->pageSlug = $page->slug;
        $this->pageContent = $page->content;
        $this->isPublished = $page->is_published;
    }

    public function updatePage()
    {
        $this->validate([
            'pageTitle' => 'required|string|max:255',
            'pageSlug' => 'required|string|max:255|unique:pages,slug,' . $this->page->id,
            'pageContent' => 'required|string',
            'isPublished' => 'boolean',
        ]);

        $this->page->update([
            'title' => $this->pageTitle,
            'slug' => $this->pageSlug,
            'content' => $this->pageContent,
            'is_published' => $this->isPublished,
        ]);

        Flux::toast('Page updated successfully!', 'Success', variant: 'success');
        return redirect()->route('acp.index', ['tab' => 'page-manager']);
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Edit Page</flux:heading>

    <form wire:submit.prevent="updatePage">
        <div class="space-y-6">
            <flux:input wire:model="pageTitle" label="Page Title" placeholder="Enter the title of the page" />
            <flux:input wire:model="pageSlug" label="Page Slug" placeholder="Enter the slug of the page" />

            <flux:editor
                label="Page Content"
                wire:model="pageContent"
            />

            <flux:switch wire:model="isPublished" label="Published" />

            <div class="w-full text-right">
                <flux:button wire:click="updatePage" variant="primary">Update Page</flux:button>
            </div>
        </div>
    </form>
</div>
