<?php

use Livewire\Attributes\{Url};
use Livewire\Volt\{Component};

new class extends Component {
    #[Url]
    public string $category = 'users';

    #[Url]
    public string $tab = 'user-manager';

    public function mount(): void
    {
        // If current category has no visible tabs, find the first visible one
        $categoryOrder = ['users', 'content', 'logs', 'config'];
        $checkers = [
            'users' => fn () => $this->hasUsersTabs(),
            'content' => fn () => $this->hasContentTabs(),
            'logs' => fn () => $this->hasLogsTabs(),
            'config' => fn () => $this->hasConfigTabs(),
        ];

        if (! ($checkers[$this->category] ?? fn () => false)()) {
            foreach ($categoryOrder as $cat) {
                if ($checkers[$cat]()) {
                    $this->category = $cat;
                    $this->tab = $this->defaultTabFor($cat);
                    break;
                }
            }
        }
    }

    public function updatedCategory(string $value): void
    {
        $this->tab = $this->defaultTabFor($value);
    }

    public function hasUsersTabs(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->can('viewAny', \App\Models\User::class)
            || $user->can('viewAny', \App\Models\MinecraftAccount::class)
            || $user->can('viewAny', \App\Models\DiscordAccount::class)
        );
    }

    public function hasContentTabs(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->can('viewAny', \App\Models\Page::class)
            || $user->can('viewAny', \App\Models\Announcement::class)
            || $user->can('viewAny', \App\Models\Meeting::class)
        );
    }

    public function hasLogsTabs(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->can('view-mc-command-log')
            || $user->can('view-activity-log')
        );
    }

    public function hasConfigTabs(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->can('viewAny', \App\Models\Role::class)
            || $user->can('viewAny', \App\Models\PrayerCountry::class)
        );
    }

    private function defaultTabFor(string $category): string
    {
        return match ($category) {
            'users' => 'user-manager',
            'content' => 'page-manager',
            'logs' => 'mc-command-log',
            'config' => 'role-manager',
            default => 'user-manager',
        };
    }
}; ?>

<div class="w-full flex flex-col gap-4">
    {{-- Top-level category tabs --}}
    <flux:tabs wire:model.live="category" variant="segmented">
        @if($this->hasUsersTabs())
            <flux:tab name="users">Users</flux:tab>
        @endif
        @if($this->hasContentTabs())
            <flux:tab name="content">Content</flux:tab>
        @endif
        @if($this->hasLogsTabs())
            <flux:tab name="logs">Logs</flux:tab>
        @endif
        @if($this->hasConfigTabs())
            <flux:tab name="config">Config</flux:tab>
        @endif
    </flux:tabs>

    {{-- Users category --}}
    @if($category === 'users')
        <flux:tab.group>
            <flux:tabs wire:model="tab" variant="segmented" size="sm">
                @can('viewAny', \App\Models\User::class)
                    <flux:tab name="user-manager">Users</flux:tab>
                @endcan
                @can('viewAny', \App\Models\MinecraftAccount::class)
                    <flux:tab name="mc-user-manager">MC Users</flux:tab>
                @endcan
                @can('viewAny', \App\Models\DiscordAccount::class)
                    <flux:tab name="discord-user-manager">Discord Users</flux:tab>
                @endcan
            </flux:tabs>

            <flux:tab.panel name="user-manager">
                @can('viewAny', \App\Models\User::class)
                    <livewire:admin-manage-users-page />
                @endcan
            </flux:tab.panel>
            <flux:tab.panel name="mc-user-manager">
                @can('viewAny', \App\Models\MinecraftAccount::class)
                    <livewire:admin-manage-mc-users-page />
                @endcan
            </flux:tab.panel>
            <flux:tab.panel name="discord-user-manager">
                @can('viewAny', \App\Models\DiscordAccount::class)
                    <livewire:admin-manage-discord-users-page />
                @endcan
            </flux:tab.panel>
        </flux:tab.group>
    @endif

    {{-- Content category --}}
    @if($category === 'content')
        <flux:tab.group>
            <flux:tabs wire:model="tab" variant="segmented" size="sm">
                @can('viewAny', \App\Models\Page::class)
                    <flux:tab name="page-manager">Pages</flux:tab>
                @endcan
                @can('viewAny', \App\Models\Announcement::class)
                    <flux:tab name="announcement-manager">Announcements</flux:tab>
                @endcan
                @can('viewAny', \App\Models\Meeting::class)
                    <flux:tab name="meeting-manager">Meetings</flux:tab>
                @endcan
            </flux:tabs>

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
        </flux:tab.group>
    @endif

    {{-- Logs category --}}
    @if($category === 'logs')
        <flux:tab.group>
            <flux:tabs wire:model="tab" variant="segmented" size="sm">
                @can('view-mc-command-log')
                    <flux:tab name="mc-command-log">MC Command Log</flux:tab>
                @endcan
                @can('view-activity-log')
                    <flux:tab name="activity-log">Activity Log</flux:tab>
                @endcan
            </flux:tabs>

            <flux:tab.panel name="mc-command-log">
                @can('view-mc-command-log')
                    <livewire:admin-manage-mc-command-log-page />
                @endcan
            </flux:tab.panel>
            <flux:tab.panel name="activity-log">
                @can('view-activity-log')
                    <livewire:admin-manage-activity-log-page />
                @endcan
            </flux:tab.panel>
        </flux:tab.group>
    @endif

    {{-- Config category --}}
    @if($category === 'config')
        <flux:tab.group>
            <flux:tabs wire:model="tab" variant="segmented" size="sm">
                @can('viewAny', \App\Models\Role::class)
                    <flux:tab name="role-manager">Roles</flux:tab>
                @endcan
                @can('viewAny', \App\Models\PrayerCountry::class)
                    <flux:tab name="prayer-manager">Prayer Nations</flux:tab>
                @endcan
            </flux:tabs>

            <flux:tab.panel name="role-manager">
                @can('viewAny', \App\Models\Role::class)
                    <livewire:admin-manage-roles-page />
                @endcan
            </flux:tab.panel>
            <flux:tab.panel name="prayer-manager">
                @can('viewAny', \App\Models\PrayerCountry::class)
                    <livewire:prayer.manage-months />
                @endcan
            </flux:tab.panel>
        </flux:tab.group>
    @endif
</div>
