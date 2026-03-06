<?php

use App\Actions\AcknowledgeAnnouncement;
use App\Jobs\SendAnnouncementNotifications;
use App\Models\Announcement;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public ?Announcement $latestAnnouncement = null;

    public function mount(): void
    {
        $this->dispatchPendingNotifications();
        $this->loadLatest();
    }

    /**
     * Lazy notification dispatch: find published announcements that haven't
     * had notifications sent yet, mark them, and queue the job.
     */
    protected function dispatchPendingNotifications(): void
    {
        $pending = Announcement::published()
            ->whereNull('notifications_sent_at')
            ->get();

        foreach ($pending as $announcement) {
            // Mark first to prevent duplicate dispatches from concurrent loads
            $announcement->update(['notifications_sent_at' => now()]);
            SendAnnouncementNotifications::dispatch($announcement);
        }
    }

    public function loadLatest(): void
    {
        $userId = auth()->id();

        $this->latestAnnouncement = Announcement::published()
            ->whereDoesntHave('acknowledgers', fn ($q) => $q->where('user_id', $userId))
            ->orderBy('published_at', 'desc')
            ->first();
    }

    public function acknowledgeAnnouncement(): void
    {
        if (! $this->latestAnnouncement) {
            return;
        }

        $this->authorize('acknowledge', $this->latestAnnouncement);

        AcknowledgeAnnouncement::run($this->latestAnnouncement, auth()->user());

        Flux::modal('view-latest-announcement')->close();
        Flux::toast('Announcement acknowledged.', 'Done', variant: 'success');

        $this->latestAnnouncement = null;
    }
}; ?>

<div>
    @if($latestAnnouncement)
        <flux:callout icon="megaphone" color="fuchsia" class="mb-6">
            <flux:callout.heading>{{ $latestAnnouncement->title }}</flux:callout.heading>
            <flux:callout.text>
                New announcement from {{ $latestAnnouncement->authorName() }}
            </flux:callout.text>
            <x-slot:actions>
                <flux:modal.trigger name="view-latest-announcement">
                    <flux:button variant="primary" size="sm">Read Announcement</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
        </flux:callout>

        <flux:modal name="view-latest-announcement" class="w-full lg:w-2/3 xl:w-1/2">
            <div class="space-y-4">
                <flux:heading size="xl">{{ $latestAnnouncement->title }}</flux:heading>

                <div class="flex items-center gap-2 text-sm text-zinc-500">
                    @if($latestAnnouncement->author)
                        @if($latestAnnouncement->author->avatarUrl())
                            <flux:avatar size="xs" src="{{ $latestAnnouncement->author->avatarUrl() }}" />
                        @endif
                        <span>Published by {{ $latestAnnouncement->author->name }}</span>
                    @endif
                    @if($latestAnnouncement->published_at)
                        <span>&middot; {{ $latestAnnouncement->published_at->format('M j, Y g:i A') }}</span>
                    @endif
                </div>

                <div class="prose prose-sm dark:prose-invert max-w-none">
                    {!! Str::markdown($latestAnnouncement->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    @can('acknowledge', $latestAnnouncement)
                        <flux:button wire:click="acknowledgeAnnouncement" variant="primary">
                            Acknowledge
                        </flux:button>
                    @endcan
                </div>
            </div>
        </flux:modal>
    @endif
</div>
