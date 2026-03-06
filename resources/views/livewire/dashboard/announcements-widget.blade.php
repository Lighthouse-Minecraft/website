<?php

use App\Models\Announcement;
use Flux\Flux;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new class extends Component {
    use WithPagination;

    public ?Announcement $selectedAnnouncement = null;

    public function getAnnouncementsProperty()
    {
        return Announcement::published()
            ->orderBy('published_at', 'desc')
            ->paginate(5, pageName: 'ann_widget_page');
    }

    public function viewAnnouncement(int $id): void
    {
        $this->selectedAnnouncement = Announcement::published()->with('author')->findOrFail($id);
        Flux::modal('view-announcement-detail')->show();
    }
}; ?>

<flux:card class="w-full">
    <flux:heading size="md" class="mb-2">Community Announcements</flux:heading>

    <flux:table :paginate="$this->announcements">
        <flux:table.columns>
            <flux:table.column>Title</flux:table.column>
            <flux:table.column>Date</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->announcements as $announcement)
                <flux:table.row wire:key="ann-widget-{{ $announcement->id }}">
                    <flux:table.cell>
                        <button wire:click="viewAnnouncement({{ $announcement->id }})" class="text-blue-600 dark:text-blue-400 hover:underline text-left">
                            {{ $announcement->title }}
                        </button>
                    </flux:table.cell>
                    <flux:table.cell class="text-sm text-zinc-500">
                        {{ $announcement->published_at?->format('M j, Y') ?? $announcement->created_at->format('M j, Y') }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="2" class="text-center text-zinc-500">No announcements yet</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if($selectedAnnouncement)
        <flux:modal name="view-announcement-detail" class="w-full lg:w-2/3 xl:w-1/2">
            <div class="space-y-4">
                <flux:heading size="xl">{{ $selectedAnnouncement->title }}</flux:heading>

                <div class="flex items-center gap-2 text-sm text-zinc-500">
                    @if($selectedAnnouncement->author)
                        @if($selectedAnnouncement->author->avatarUrl())
                            <flux:avatar size="xs" src="{{ $selectedAnnouncement->author->avatarUrl() }}" />
                        @endif
                        <span>{{ $selectedAnnouncement->author->name }}</span>
                    @endif
                    @if($selectedAnnouncement->published_at)
                        <span>&middot; {{ $selectedAnnouncement->published_at->format('M j, Y g:i A') }}</span>
                    @endif
                </div>

                <div class="prose prose-sm dark:prose-invert max-w-none">
                    {!! $selectedAnnouncement->renderedContent() !!}
                </div>
            </div>
        </flux:modal>
    @endif
</flux:card>
