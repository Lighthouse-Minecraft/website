<?php

use Livewire\Volt\Component;

new class extends Component {
    public $activeAnnouncements;
    public $activeAnnouncementTab = 'active-announcements';

    public function mount()
    {
        $this->activeAnnouncements = \App\Models\Announcement::where('is_published', true)->get();
    }

}; ?>

<flux:card class="w-full">
    <!-- START OF ANNOUNCEMENTS WIDGET -->
    <flux:heading size="md" class="mb-4">Community Announcements</flux:heading>

    <flux:tab.group>
        <flux:tabs wire:model="activeAnnouncementTab">
            <flux:tab name="active-announcements">Current</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="active-announcements">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Announcement</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($activeAnnouncements as $announcement)
                        <flux:table.row>
                            <flux:table.cell>
                                <flux:link href="{{ route('announcements.show', $announcement->id) }}">{{  $announcement->title }}</flux:link>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:tab.panel>

    </flux:tab.group>
    <!-- END OF ANNOUNCEMENTS WIDGET -->
</flux:card>
