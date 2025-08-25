<?php

use App\Models\Announcement;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $activeAnnouncementTab = 'active-announcements';
    public int $perPage = 4;

    public function getAnnouncementsProperty()
    {
        return Announcement::query()
            ->where('is_published', true)
            ->with(['author', 'categories', 'tags'])
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage, ['*'], 'ann_widget_page'); // unique page name
    }

}; ?>

<flux:card class="w-full">
    <!-- START OF ANNOUNCEMENTS WIDGET -->
    <flux:heading size="md" class="mb-2">Community Announcements</flux:heading>

    <flux:table :paginate="$this->announcements">
        <flux:table.rows>
            @foreach ($this->announcements as $announcement)
                <flux:table.row>
                    <flux:table.cell>
                        <flux:link href="{{ route('announcements.show', ['id' => $announcement->id, 'from' => 'dashboard']) }}">{{  $announcement->title }}</flux:link>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <!-- END OF ANNOUNCEMENTS WIDGET -->
</flux:card>
