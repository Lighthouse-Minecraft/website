<?php

use Livewire\Volt\Component;
use App\Models\Page;

new class extends Component {
    public $pages;

    public function mount()
    {
        $this->pages = Page::all();
    }

}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Manage Pages</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Title</flux:table.column>
            <flux:table.column>Slug</flux:table.column>
            <flux:table.column>Published</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach($this->pages as $page)
                <flux:table.row>
                    <flux:table.cell>{{ $page->title }}</flux:table.cell>
                    <flux:table.cell>{{ $page->slug }}</flux:table.cell>
                    <flux:table.cell>{{ $page->is_published ? 'Yes' : 'No' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button wire:navigate href="{{ route('admin.pages.edit', $page) }}" size="xs">Edit</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="w-full text-right">
        <flux:spacer />
        <flux:button wire:navigate href="{{ route('admin.pages.create') }}" variant="primary">Create New Page</flux:button>
    </div>
</div>
