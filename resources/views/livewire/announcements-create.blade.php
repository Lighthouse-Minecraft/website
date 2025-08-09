
<?php

use Livewire\Volt\{Component};
use Flux\{Flux};

new class extends Component {
    public $announcementTitle = '';
    public $announcementSlug = '';
    public $announcementContent = '';
    public $isPublished = false;

    public function saveAnnouncement()
    {
        $this->validate([
            'announcementTitle' => 'required|string|max:255',
            'announcementContent' => 'required|string',
            'isPublished' => 'boolean',
        ]);

        \App\Models\Announcement::create([
            'title' => $this->announcementTitle,
            'content' => $this->announcementContent,
            'is_published' => $this->isPublished,
            'author_id' => auth()->id(),
        ]);

        Flux::toast('Announcement created successfully!', 'Success', variant: 'success');
        return redirect()->route('acp.index', ['tab' => 'announcement-manager']);
    }
}; ?>


<div class="space-y-6">
    <flux:heading size="xl">Create New Announcement</flux:heading>
    <form wire:submit.prevent="saveAnnouncement">
        <div class="space-y-6">
            <flux:input wire:model="announcementTitle" label="Announcement Title" placeholder="Enter the title of the announcement" />

            <flux:editor label="Announcement Content" wire:model="announcementContent" />

            <flux:switch wire:model="isPublished" label="Published" />

            <div class="w-full text-right">
                <flux:button wire:click="saveAnnouncement" variant="primary">Save Announcement</flux:button>
            </div>
        </div>
    </form>
</div>
