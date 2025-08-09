<?php

use App\Models\{Announcement};
use Flux\{Flux};
use Livewire\Volt\{Component};

new class extends Component {
    public Announcement $announcement;
    public $announcementTitle = '';
    public $announcementSlug = '';
    public $announcementContent = '';
    public $isPublished = false;

    public function mount(Announcement $announcement)
    {
        $this->announcement = $announcement;
        $this->announcementTitle = $announcement->title;
        $this->announcementSlug = $announcement->slug;
        $this->announcementContent = $announcement->content;
        $this->isPublished = (bool) $announcement->is_published;
    }

    public function updateAnnouncement()
    {
        $this->validate([
            'announcementTitle' => 'required|string|max:255',
            'announcementContent' => 'required|string',
            'isPublished' => 'boolean',
        ]);

        $this->announcement->update([
            'title' => $this->announcementTitle,
            'content' => $this->announcementContent,
            'is_published' => $this->isPublished,
        ]);

        Flux::toast('Announcement updated successfully!', 'Success', variant: 'success');
        return redirect()->route('acp.index', ['tab' => 'announcement-manager']);
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Edit Announcement</flux:heading>

    <form wire:submit.prevent="updateAnnouncement">
        <div class="space-y-6">
            <flux:input wire:model="announcementTitle" label="Announcement Title" placeholder="Enter the title of the announcement" />

            <flux:editor
                label="Announcement Content"
                wire:model="announcementContent"
            />

            @if($isPublished)
                <flux:checkbox label="Published" wire:model="isPublished" checked />
            @else
                <flux:checkbox label="Published" wire:model="isPublished" />
            @endif

            <div class="w-full text-right">
                <flux:button wire:navigate href="{{ route('acp.index', ['tab' => 'announcement-manager']) }}" class="mx-4" >Cancel</flux:button>
                <flux:button wire:click="updateAnnouncement" variant="primary">Update Announcement</flux:button>
            </div>
        </div>
    </form>
</div>
