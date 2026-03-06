<?php

use App\Models\Announcement;
use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new class extends Component {
    use WithPagination;

    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    // Create form
    public string $newTitle = '';
    public string $newContent = '';
    public bool $newIsPublished = false;
    public ?string $newPublishedAt = null;
    public ?string $newExpiredAt = null;

    // Edit form
    public ?int $editId = null;
    public string $editTitle = '';
    public string $editContent = '';
    public bool $editIsPublished = false;
    public ?string $editPublishedAt = null;
    public ?string $editExpiredAt = null;

    // Preview
    public bool $showCreatePreview = false;
    public bool $showEditPreview = false;

    protected function userTimezone(): string
    {
        return auth()->user()->timezone ?? 'America/New_York';
    }

    /**
     * Convert a datetime-local input value (in user's timezone) to UTC for storage.
     */
    protected function toUtc(?string $datetime): ?Carbon
    {
        if (! filled($datetime)) {
            return null;
        }

        return Carbon::parse($datetime, new CarbonTimeZone($this->userTimezone()))->utc();
    }

    /**
     * Convert a UTC datetime to the user's timezone for datetime-local input display.
     */
    protected function toLocal(?\DateTimeInterface $datetime): ?string
    {
        if (! $datetime) {
            return null;
        }

        return Carbon::instance($datetime)->setTimezone($this->userTimezone())->format('Y-m-d\TH:i');
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function getAnnouncementsProperty()
    {
        return Announcement::orderBy($this->sortBy, $this->sortDirection)
            ->with(['author.roles', 'author.minecraftAccounts', 'author.discordAccounts'])
            ->paginate(10);
    }

    public function createAnnouncement(): void
    {
        $this->authorize('create', Announcement::class);

        $this->validate([
            'newTitle' => 'required|string|max:255',
            'newContent' => 'required|string|max:10000',
            'newIsPublished' => 'boolean',
            'newPublishedAt' => 'nullable|date',
            'newExpiredAt' => 'nullable|date|after:newPublishedAt',
        ]);

        $isPublished = $this->newIsPublished || filled($this->newPublishedAt);

        Announcement::create([
            'title' => $this->newTitle,
            'content' => $this->newContent,
            'author_id' => auth()->id(),
            'is_published' => $isPublished,
            'published_at' => $isPublished ? ($this->toUtc($this->newPublishedAt) ?? now()) : null,
            'expired_at' => $this->toUtc($this->newExpiredAt),
        ]);

        Flux::modal('create-announcement-modal')->close();
        Flux::toast('Announcement created.', 'Created', variant: 'success');
        $this->reset(['newTitle', 'newContent', 'newIsPublished', 'newPublishedAt', 'newExpiredAt', 'showCreatePreview']);
    }

    public function openEditModal(int $id): void
    {
        $announcement = Announcement::findOrFail($id);
        $this->authorize('update', $announcement);

        $this->editId = $id;
        $this->editTitle = $announcement->title;
        $this->editContent = $announcement->content;
        $this->editIsPublished = $announcement->is_published;
        $this->editPublishedAt = $this->toLocal($announcement->published_at);
        $this->editExpiredAt = $this->toLocal($announcement->expired_at);
        $this->showEditPreview = false;
    }

    public function updateAnnouncement(): void
    {
        $announcement = Announcement::findOrFail($this->editId);
        $this->authorize('update', $announcement);

        $this->validate([
            'editTitle' => 'required|string|max:255',
            'editContent' => 'required|string|max:10000',
            'editIsPublished' => 'boolean',
            'editPublishedAt' => 'nullable|date',
            'editExpiredAt' => 'nullable|date|after:editPublishedAt',
        ]);

        $isPublished = $this->editIsPublished || filled($this->editPublishedAt);

        $announcement->update([
            'title' => $this->editTitle,
            'content' => $this->editContent,
            'is_published' => $isPublished,
            'published_at' => $isPublished ? ($this->toUtc($this->editPublishedAt) ?? $announcement->published_at ?? now()) : null,
            'expired_at' => $this->toUtc($this->editExpiredAt),
        ]);

        Flux::modal('edit-announcement-modal')->close();
        Flux::toast('Announcement updated.', 'Updated', variant: 'success');
        $this->reset(['editId', 'editTitle', 'editContent', 'editIsPublished', 'editPublishedAt', 'editExpiredAt', 'showEditPreview']);
    }

    public function deleteAnnouncement(int $id): void
    {
        $announcement = Announcement::findOrFail($id);
        $this->authorize('delete', $announcement);

        $announcement->delete();
        Flux::toast('Announcement deleted.', 'Deleted', variant: 'success');
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Manage Announcements</flux:heading>

    <flux:table :paginate="$this->announcements">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'title'" :direction="$sortDirection" wire:click="sort('title')">Title</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'author_id'" :direction="$sortDirection" wire:click="sort('author_id')">Author</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Created</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'is_published'" :direction="$sortDirection" wire:click="sort('is_published')">Status</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->announcements as $announcement)
                <flux:table.row wire:key="ann-row-{{ $announcement->id }}">
                    <flux:table.cell class="font-medium">{{ $announcement->title }}</flux:table.cell>
                    <flux:table.cell class="flex items-center gap-2">
                        @if($announcement->author)
                            @if($announcement->author->avatarUrl())
                                <flux:avatar size="xs" src="{{ $announcement->author->avatarUrl() }}" />
                            @endif
                            {{ $announcement->author->name }}
                        @else
                            <flux:text variant="subtle">Unknown</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $announcement->created_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>
                        @if($announcement->isExpired())
                            <flux:badge size="sm" color="zinc">Expired</flux:badge>
                        @elseif($announcement->is_published && $announcement->published_at?->isFuture())
                            <flux:badge size="sm" color="blue">Scheduled</flux:badge>
                        @elseif($announcement->is_published)
                            <flux:badge size="sm" color="green">Published</flux:badge>
                        @else
                            <flux:badge size="sm" color="yellow">Draft</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            @can('update', $announcement)
                                <flux:modal.trigger wire:click="openEditModal({{ $announcement->id }})" name="edit-announcement-modal">
                                    <flux:button size="sm" icon="pencil-square">Edit</flux:button>
                                </flux:modal.trigger>
                            @endcan
                            @can('delete', $announcement)
                                <flux:button size="sm" icon="trash" variant="ghost" wire:click="deleteAnnouncement({{ $announcement->id }})" wire:confirm="Delete this announcement? This cannot be undone.">Delete</flux:button>
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-gray-500">No announcements found</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="w-full text-right">
        @can('create', Announcement::class)
            <flux:modal.trigger name="create-announcement-modal">
                <flux:button variant="primary">Create Announcement</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    {{-- Create Modal --}}
    <flux:modal name="create-announcement-modal" variant="flyout" class="space-y-6">
        <flux:heading size="xl">Create Announcement</flux:heading>
        <form wire:submit.prevent="createAnnouncement">
            <div class="space-y-6">
                <flux:input label="Title" wire:model="newTitle" required placeholder="Announcement title" />

                <flux:field>
                    <div class="flex items-center justify-between">
                        <flux:label>Content (Markdown)</flux:label>
                        <flux:button size="xs" variant="ghost" type="button" wire:click="$toggle('showCreatePreview')">
                            {{ $showCreatePreview ? 'Edit' : 'Preview' }}
                        </flux:button>
                    </div>
                    @if($showCreatePreview)
                        <div class="prose prose-sm dark:prose-invert max-w-none border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 min-h-[150px]">
                            {!! Str::markdown($newContent, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                        </div>
                    @else
                        <flux:textarea wire:model="newContent" rows="10" placeholder="Write your announcement using markdown..." />
                    @endif
                    <flux:error name="newContent" />
                </flux:field>

                <flux:switch wire:model="newIsPublished" label="Publish now" />

                <flux:field>
                    <flux:label>Schedule for later (optional, Eastern Time)</flux:label>
                    <flux:input wire:model="newPublishedAt" type="datetime-local" />
                    <flux:description>Set a date to schedule publishing. This will publish automatically at the specified time.</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Expires At (optional, Eastern Time)</flux:label>
                    <flux:input wire:model="newExpiredAt" type="datetime-local" />
                    <flux:description>Auto-unpublish after this date. Leave blank for no expiration.</flux:description>
                </flux:field>

                <flux:button type="submit" variant="primary">Create</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal name="edit-announcement-modal" variant="flyout" class="space-y-6">
        <flux:heading size="xl">Edit Announcement</flux:heading>
        <form wire:submit.prevent="updateAnnouncement">
            <div class="space-y-6">
                <flux:input label="Title" wire:model="editTitle" required />

                <flux:field>
                    <div class="flex items-center justify-between">
                        <flux:label>Content (Markdown)</flux:label>
                        <flux:button size="xs" variant="ghost" type="button" wire:click="$toggle('showEditPreview')">
                            {{ $showEditPreview ? 'Edit' : 'Preview' }}
                        </flux:button>
                    </div>
                    @if($showEditPreview)
                        <div class="prose prose-sm dark:prose-invert max-w-none border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 min-h-[150px]">
                            {!! Str::markdown($editContent, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                        </div>
                    @else
                        <flux:textarea wire:model="editContent" rows="10" />
                    @endif
                    <flux:error name="editContent" />
                </flux:field>

                <flux:switch wire:model="editIsPublished" label="Published" />

                <flux:field>
                    <flux:label>Publish At (Eastern Time)</flux:label>
                    <flux:input wire:model="editPublishedAt" type="datetime-local" />
                </flux:field>

                <flux:field>
                    <flux:label>Expires At (optional, Eastern Time)</flux:label>
                    <flux:input wire:model="editExpiredAt" type="datetime-local" />
                </flux:field>

                <flux:button type="submit" variant="primary">Save Changes</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
