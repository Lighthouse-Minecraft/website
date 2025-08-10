<?php

use App\Models\{Announcement, Category, Tag};
use Flux\{Flux, Option};
use Illuminate\Support\{Arr, Str, Carbon, Collection, Facades};
use Livewire\Volt\{Component};

new class extends Component {
    public Announcement $announcement;
    public string $announcementTitle = '';
    public string $announcementContent = '';
    public array $selectedTags = [];
    public array $selectedCategories = [];
    public bool $isPublished = false;
    public ?Carbon $published_at = null;

    public function mount(Announcement $announcement)
    {
        $this->announcement = $announcement;
        $this->announcementTitle = $announcement->title;
        $this->announcementContent = $announcement->content;
        $this->isPublished = (bool) $announcement->is_published;
        $this->published_at = $announcement->published_at;
        $this->selectedTags = $announcement->tags->pluck('id')->toArray();
        $this->selectedCategories = $announcement->categories->pluck('id')->toArray();
    }

    // Computed options to avoid DB queries in the view each render
    public function getTagOptionsProperty(): array
    {
        return Tag::query()
            ->get(['id', 'name'])
            ->map(fn ($t) => ['label' => $t->name, 'value' => (int) $t->id])
            ->toArray();
    }

    public function getCategoryOptionsProperty(): array
    {
        return Category::query()
            ->get(['id', 'name'])
            ->map(fn ($c) => ['label' => $c->name, 'value' => (int) $c->id])
            ->toArray();
    }

    public function updateAnnouncement()
    {
        // Validate incoming data
        $this->validate([
            'announcementTitle' => 'required|string|max:255',
            'announcementContent' => 'required|string|max:5000',
            'selectedTags' => 'array',
            'selectedCategories' => 'array',
            'isPublished' => 'boolean',
            'published_at' => 'date|nullable',
        ]);

        // Force types to ensure correct data types
        $this->announcementTitle = (string) $this->announcementTitle;
        $this->announcementContent = (string) $this->announcementContent;
        $this->selectedTags = array_map('intval', Arr::wrap($this->selectedTags));
        $this->selectedCategories = array_map('intval', Arr::wrap($this->selectedCategories));
        $this->isPublished = (bool) $this->isPublished;
        $this->published_at = $this->published_at ? Carbon::parse($this->published_at) : null;

        $this->announcement->update([
            'title' => $this->announcementTitle,
            'content' => $this->announcementContent,
            'author_id' => auth()->id(),
            'tags' => $this->selectedTags,
            'categories' => $this->selectedCategories,
            'is_published' => $this->isPublished,
            'published_at' => $this->isPublished ? ($this->published_at ?? now()) : null,
        ]);

        Flux::toast('Announcement updated successfully!', 'Success', variant: 'success');
        return redirect()->route('acp.index', ['tab' => 'announcement-manager']);
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Edit Announcement</flux:heading>
    <form wire:submit.prevent="updateAnnouncement">
        <div class="space-y-6">
            <flux:input label="Announcement Title" wire:model="announcementTitle" placeholder="Enter the title of the announcement" />

            <flux:editor label="Announcement Content" wire:model="announcementContent" />

            <select name="tags">
                <option value="">Select Tags</option>
                @foreach ($this->getTagOptionsProperty() as $tag)
                    <option value="{{ $tag['value'] }}">{{ $tag['label'] }}</option>
                @endforeach
            </select>

            <select name="categories">
                <option value="">Select Categories</option>
                @foreach ($this->getCategoryOptionsProperty() as $category)
                    <option value="{{ $category['value'] }}">{{ $category['label'] }}</option>
                @endforeach
            </select>

            <flux:checkbox label="Published" wire:model="isPublished" />

            {{-- <flux:input label="Published At" wire:model="published_at" type="datetime-local" /> --}}

            <div class="w-full text-right">
                <flux:button wire:navigate href="{{ route('acp.index', ['tab' => 'announcement-manager']) }}" class="mx-4">Cancel</flux:button>
                <flux:button wire:click="updateAnnouncement" icon="document-check" variant="primary">Update Announcement</flux:button>
            </div>
        </div>
    </form>
</div>
