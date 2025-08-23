<?php

use Livewire\Attributes\{Url};
use Livewire\Volt\{Component};

new class extends Component {
    #[Url]
    public $tab = 'user-manager';

    public function reactive()
    {
        return ['tab'];
    }
}; ?>

<div class="w-full flex">
    <flux:tab.group>
        <flux:tabs wire:model="tab" variant="segmented" size="sm">
            @can('viewAny', \App\Models\User::class)
                <flux:tab name="user-manager">Users</flux:tab>
            @endcan

            @can('viewAny', \App\Models\Role::class)
                <flux:tab name="role-manager">Roles</flux:tab>
            @endcan

            @can('viewAny', \App\Models\Page::class)
                <flux:tab name="page-manager">Pages</flux:tab>
            @endcan

            @can('viewAny', \App\Models\Announcement::class)
                <flux:tab name="announcement-manager">Announcements</flux:tab>
            @endcan

            @can('viewAny', \App\Models\Meeting::class)
                <flux:tab name="meeting-manager">Meetings</flux:tab>
            @endcan

            @can('viewAny', \App\Models\PrayerCountry::class)
                <flux:tab name="prayer-manager">Prayer Nations</flux:tab>
            @endcan
        </flux:tabs>


        <flux:tab.panel name="user-manager">
            @can('viewAny', \App\Models\User::class)
                <livewire:admin-manage-users-page />
            @endcan
        </flux:tab.panel>

        <flux:tab.panel name="role-manager">
            @can('viewAny', \App\Models\Role::class)
                <livewire:admin-manage-roles-page />
            @endcan
        </flux:tab.panel>

        <flux:tab.panel name="page-manager">
            @can('viewAny', \App\Models\Page::class)
                <livewire:admin-manage-pages-page />
            @endcan
        </flux:tab.panel>

        <flux:tab.panel name="announcement-manager">
            @can('viewAny', \App\Models\Announcement::class)
                <livewire:admin-manage-announcements-page />
            @endcan
        </flux:tab.panel>

        <flux:tab.panel name="meeting-manager">
            @can('viewAny', \App\Models\Meeting::class)
                <livewire:meetings.list />
            @endcan
        </flux:tab.panel>

        <flux:tab.panel name="prayer-manager">
            @can('viewAny', \App\Models\PrayerCountry::class)
                <livewire:prayer.manage-months />
            @endcan
        </flux:tab.panel>

    </flux:tab.group>


</div>
