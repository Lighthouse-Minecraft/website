<?php

use App\Actions\AcknowledgeAnnouncement;
use App\Enums\MembershipLevel;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Flux\Flux;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new class extends Component {
    public $announcements;

    public function mount()
    {
        $this->announcements = Announcement::where('is_published', true)
            ->whereDoesntHave('acknowledgers', function ($query) {
                $query->where('users.id', auth()->id());
            })
            ->with(['author', 'categories', 'tags'])
            ->get();
    }

    public function acknowledgeAnnouncement($announcementId)
    {
        $announcement = Announcement::findOrFail($announcementId);

        if (auth()->user()->can('acknowledge', $announcement)) {
            AcknowledgeAnnouncement::run($announcement, auth()->user());
            Flux::toast('Announcement acknowledged successfully.', 'success');
        } else {
            Flux::toast('You do not have permission to acknowledge this announcement.', 'error');
        }

        Flux::modal('view-announcement-' . $announcementId)->close();
        return redirect()->route('dashboard');
    }
};

?>

<div class="w-full space-y-6">
    @foreach($announcements as $announcement)
        <flux:callout color="fuchsia" inline class="mb-6">
            <flux:callout.heading>{{  $announcement->title }}</flux:callout.heading>

            <flux:callout.text>
                {!! nl2br(e($announcement->excerpt())) !!}
            </flux:callout.text>

            <x-slot name="actions">
                <flux:modal.trigger name="view-announcement-{{ $announcement->id }}">
                    <flux:button>Read Full Announcement</flux:button>
                </flux:modal.trigger>
            </x-slot>

            <flux:modal name="view-announcement-{{ $announcement->id }}" class="w-full md:w-3/4 xl:w-1/2">
                <flux:heading size="xl" class="mb-4">{{ $announcement->title }}</flux:heading>
                <div id="editor_content" class="prose max-w-none">
                    {!!  $announcement->content !!}
                </div>

                @can('acknowledge', $announcement)
                    <div class="w-full text-right mb-4">
                        <flux:button wire:click="acknowledgeAnnouncement({{ $announcement->id }})" size="sm" variant="primary">
                            Acknowledge Announcement
                        </flux:button>
                    </div>
                @endcan

                <flux:separator />
                <livewire:announcements.author-info :announcement="$announcement" />
                <livewire:announcements.categories :announcement="$announcement" />
                <livewire:announcements.tags :announcement="$announcement" />
                <livewire:announcements.comments :announcement="$announcement" />
            </flux:modal>
        </flux:callout>
    @endforeach
</div>
