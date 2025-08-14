<?php

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Page;
use App\Models\Role;
use App\Models\Tag;
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
        <flux:tabs wire:model="tab" variant="pills">
            @can('viewAny', User::class)
                <flux:tab name="user-manager">User Manager</flux:tab>
            @endcan

            @can('viewAny', Role::class)
                <flux:tab name="role-manager">Role Manager</flux:tab>
            @endcan

            @can('viewAny', Page::class)
                <flux:tab name="page-manager">Page Manager</flux:tab>
            @endcan

            @can('viewAny', Announcement::class)
                <flux:tab name="announcement-manager">Announcement Manager</flux:tab>
            @endcan

            @can('viewAny', Blog::class)
                <flux:tab name="blog-manager">Blog Manager</flux:tab>
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
    </flux:tab.group>
</div>
