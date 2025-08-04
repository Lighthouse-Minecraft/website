<?php

use Livewire\Volt\Component;
use Flux\Flux;

new class extends Component {
    public $pageTitle = '';
    public $pageSlug = '';
    public $pageContent = '';
    public $isPublished = false;

    public function savePage()
    {
        $this->validate([
            'pageTitle' => 'required|string|max:255',
            'pageSlug' => 'required|string|max:255|unique:pages,slug',
            'pageContent' => 'required|string',
        ]);

        \App\Models\Page::create([
            'title' => $this->pageTitle,
            'slug' => $this->pageSlug,
            'content' => $this->pageContent,
            'is_published' => $this->isPublished,
        ]);

        Flux::toast('Page created successfully!', 'Success', variant: 'success');
        return redirect()->route('acp.index', ['tab' => 'page-manager']);
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Create New Page</flux:heading>

    <form wire:submit.prevent="savePage">
        <div class="space-y-6">
            <flux:input wire:model="pageTitle" label="Page Title" placeholder="Enter the title of the page" />
            <flux:input wire:model="pageSlug" label="Page Slug" placeholder="Enter the slug of the page" />

            <flux:editor
                label="Page Content"
                wire:model="pageContent"
            />

            <flux:switch wire:model="isPublished" label="Published" />

            <div class="w-full text-right">
                <flux:button wire:click="savePage" variant="primary">Save Page</flux:button>
            </div>
        </div>
    </form>

</div>
