<?php

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Meeting;
use App\Models\Page;
use App\Models\Role;
use App\Models\Tag;
use App\Models\Taxonomy;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

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
            @can('viewAny', User::class)
                <flux:tab name="user-manager">Users</flux:tab>
            @endcan

            @can('viewAny', Role::class)
                <flux:tab name="role-manager">Roles</flux:tab>
            @endcan

            @can('viewAny', Page::class)
                <flux:tab name="page-manager">Pages</flux:tab>
            @endcan

            @can('viewAny', Announcement::class)
                <flux:tab name="announcement-manager">Announcements</flux:tab>
            @endcan

            @can('viewAny', Meeting::class)
                <flux:tab name="meeting-manager">Meetings</flux:tab>
            @endcan

            @can('viewAny', \App\Models\PrayerCountry::class)
                <flux:tab name="prayer-manager">Prayer Nations</flux:tab>
            @endcan

            @can('viewAny', Blog::class)
                <flux:tab name="blog-manager">Blog Manager</flux:tab>
            @endcan

            @can('viewAny', Taxonomy::class)
                <flux:tab name="taxonomy-manager">Taxonomy Manager</flux:tab>
            @endcan

            @can('viewAny', Comment::class)
                <flux:tab name="comment-manager">Comment Manager</flux:tab>
            @endcan
        </flux:tabs>

        <flux:tab.panel name="user-manager">
            @can('viewAny', User::class)
                <livewire:admin-manage-users-page />
            @endcan
        </flux:tab.panel>

        <flux:tab.panel name="role-manager">
            @can('viewAny', Role::class)
                <livewire:admin-manage-roles-page />
            @endcan
        </flux:tab.panel>

        <flux:tab.panel name="page-manager">
            @can('viewAny', Page::class)
                <livewire:admin-manage-pages-page />
            @endcan
        </flux:tab.panel>

        <flux:tab.panel name="prayer-manager">
            @can('viewAny', \App\Models\PrayerCountry::class)
                <livewire:prayer.manage-months />
            @endcan
        </flux:tab.panel>
        
        <flux:tab.panel name="announcement-manager">
            @can('viewAny', Announcement::class)
                <livewire:admin-manage-announcements-page />
            @endcan
        </flux:tab.panel>

        <flux:tab.panel name="blog-manager">
            @can('viewAny', Blog::class)
                <livewire:admin-manage-blogs-page />
            @endcan
        </flux:tab.panel>

        <flux:tab.panel name="taxonomy-manager">
            @can('viewAny', Taxonomy::class)
                <livewire:admin-manage-taxonomies-page />
            @endcan
        </flux:tab.panel>

        <flux:tab.panel name="comment-manager">
            @can('viewAny', Comment::class)
                <livewire:admin-manage-comments-page />
            @endcan
        </flux:tab.panel>
    </flux:tab.group>
</div>
