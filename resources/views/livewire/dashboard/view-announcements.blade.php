<?php

use App\Enums\{MembershipLevel};
use App\Models\{Announcement, Category, Comment, Role, Tag, User};
use Flux\{Flux};
use Livewire\{WithPagination};
use Livewire\Volt\{Component};

new class extends Component {
    public $announcements;

    public function mount()
    {
        $this->announcements = Announcement::where('is_published', true)->with('author', 'categories', 'tags')->get();
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

                <flux:separator />
                <livewire:announcements.author-info :announcement="$announcement" />
                <livewire:announcements.categories :announcement="$announcement" />
                <livewire:announcements.tags :announcement="$announcement" />
                <livewire:announcements.comments :announcement="$announcement" />
            </flux:modal>
        </flux:callout>
    @endforeach
</div>
