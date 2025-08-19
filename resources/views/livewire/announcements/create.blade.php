
<?php

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Tag;
use Flux\Flux;
use Flux\Option;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades;
use Livewire\Volt\Component;

new class extends Component {
    public string $announcementTitle = '';
    public string $announcementContent = '';
    public array $selectedTags = [];
    public array $selectedCategories = [];
    public bool $isPublished = false;
    public ?Carbon $published_at = null;

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

    public function saveAnnouncement()
    {
        // Validate incoming data
        $this->validate([
            'announcementTitle' => 'required|string|max:255',
            'announcementContent' => 'required|string|max:5000',
            'selectedTags' => ['array'],
            'selectedTags.*' => ['integer', 'exists:tags,id'],
            'selectedCategories' => ['array'],
            'selectedCategories.*' => ['integer', 'exists:categories,id'],
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

        $announcement = Announcement::create([
            'title' => $this->announcementTitle,
            'content' => $this->announcementContent,
            'author_id' => auth()->id(),
            'is_published' => $this->isPublished,
            'published_at' => $this->isPublished ? ($this->published_at ?? now()) : null,
        ]);

        // Sync pivot relations
        $announcement->tags()->sync($this->selectedTags);
        $announcement->categories()->sync($this->selectedCategories);

        Flux::toast('Announcement created successfully!', 'Success', variant: 'success');
        return redirect()->route('acp.index', ['tab' => 'announcement-manager']);
    }
}; ?>


<div class="space-y-6">
    <flux:heading size="xl">Create New Announcement</flux:heading>
    <form wire:submit.prevent="saveAnnouncement">
        <div class="space-y-6">
            <flux:input wire:model="announcementTitle" placeholder="Enter title..." class="bg-transparent text-lg font-semibold" />

            <flux:editor wire:model="announcementContent" class="bg-transparent" style="text-align: justify;" />

            <flux:field>
                <flux:label>Tags</flux:label>
                <flux:select variant="listbox" multiple searchable indicator="checkbox" wire:model.live="selectedTags">
                    <x-slot name="button">
                        <flux:select.button class="w-full max-w-xl" placeholder="Select tags" :invalid="$errors->has('selectedTags')" />
                    </x-slot>
                    @foreach ($this->getTagOptionsProperty() as $tag)
                        <flux:select.option value="{{ $tag['value'] }}">{{ $tag['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="selectedTags" />
            </flux:field>

            <flux:field>
                <flux:label>Categories</flux:label>
                <flux:select variant="listbox" multiple searchable indicator="checkbox" wire:model.live="selectedCategories">
                    <x-slot name="button">
                        <flux:select.button class="w-full max-w-xl" placeholder="Select categories" :invalid="$errors->has('selectedCategories')" />
                    </x-slot>
                    @foreach ($this->getCategoryOptionsProperty() as $category)
                        <flux:select.option value="{{ $category['value'] }}">{{ $category['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="selectedCategories" />
            </flux:field>

            <flux:checkbox label="Published" wire:model="isPublished" />

            <div class="w-full text-right">
                <flux:button wire:click="saveAnnouncement" icon="document-check" variant="primary">Save Announcement</flux:button>
            </div>
        </div>
    </form>
</div>
