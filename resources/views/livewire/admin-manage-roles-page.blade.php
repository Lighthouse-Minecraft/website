<?php

use Livewire\Volt\Component;
use Illuminate\Support\Str;
use Flux\Flux;

new class extends Component {

    public $newRoleName = '';
    public $newRoleColor = '';
    public $newRoleDescription = '';
    public $newRoleIcon = '';

    public $updateRoleName = '';
    public $updateRoleColor = '';
    public $updateRoleDescription = '';
    public $updateRoleIcon = '';

    public $allowedIcons = [
        'academic-cap', 'adjustments-horizontal', 'adjustments-vertical', 'archive-box', 'archive-box-arrow-down',
        'archive-box-x-mark', 'arrow-down', 'arrow-down-circle', 'arrow-down-left', 'arrow-down-on-square',
        'arrow-down-on-square-stack', 'arrow-down-right', 'arrow-down-tray', 'arrow-left', 'arrow-left-circle',
        'arrow-long-down', 'arrow-long-left', 'arrow-long-right', 'arrow-long-up', 'arrow-path', 'arrow-path-rounded-square',
        'arrow-right', 'arrow-right-circle', 'arrow-small-down', 'arrow-small-left', 'arrow-small-right', 'arrow-small-up',
        'arrow-top-right-on-square', 'arrow-trending-down', 'arrow-trending-up', 'arrow-up', 'arrow-up-circle',
        'arrow-up-left', 'arrow-up-on-square', 'arrow-up-on-square-stack', 'arrow-up-right', 'arrow-up-tray', 'arrow-uturn-down',
        'arrow-uturn-left', 'arrow-uturn-right', 'arrow-uturn-up', 'arrows-pointing-in', 'arrows-pointing-out',
        'arrows-right-left', 'arrows-up-down', 'at-symbol', 'backspace', 'backward', 'banknotes', 'bars-2', 'bars-3',
        'bars-3-bottom-left', 'bars-3-bottom-right', 'bars-3-center-left', 'bars-4', 'bars-arrow-down', 'bars-arrow-up',
        'battery-0', 'battery-100', 'battery-50', 'beaker', 'bell', 'bell-alert', 'bell-slash', 'bell-snooze', 'bolt',
        'bolt-slash', 'book-open', 'bookmark', 'bookmark-slash', 'bookmark-square', 'briefcase', 'bug-ant', 'building-library',
        'building-office', 'building-office-2', 'building-storefront', 'cake', 'calculator', 'calendar', 'calendar-days',
        'camera', 'chart-bar', 'chart-bar-square', 'chart-pie', 'chat-bubble-bottom-center', 'chat-bubble-bottom-center-text',
        'chat-bubble-left', 'chat-bubble-left-ellipsis', 'chat-bubble-left-right', 'chat-bubble-oval-left', 'chat-bubble-oval-left-ellipsis',
        'check', 'check-badge', 'check-circle', 'chevron-double-down', 'chevron-double-left', 'chevron-double-right',
        'chevron-double-up', 'chevron-down', 'chevron-left', 'chevron-right', 'chevron-up', 'chevron-up-down', 'circle-stack',
        'clipboard', 'clipboard-document', 'clipboard-document-check', 'clipboard-document-list', 'clock', 'cloud', 'cloud-arrow-down',
        'cloud-arrow-up', 'code-bracket', 'code-bracket-square', 'cog', 'cog-6-tooth', 'cog-8-tooth', 'command-line', 'computer-desktop',
        'cpu-chip', 'credit-card', 'cube', 'cube-transparent', 'currency-bangladeshi', 'currency-dollar', 'currency-euro',
        'currency-pound', 'currency-rupee', 'currency-yen', 'cursor-arrow-rays', 'cursor-arrow-ripple', 'device-phone-mobile',
        'device-tablet', 'document', 'document-arrow-down', 'document-arrow-up', 'document-chart-bar', 'document-check',
        'document-duplicate', 'document-magnifying-glass', 'document-minus', 'document-plus', 'document-text', 'ellipsis-horizontal',
        'ellipsis-horizontal-circle', 'ellipsis-vertical', 'envelope', 'envelope-open', 'exclamation-circle', 'exclamation-triangle',
        'eye', 'eye-dropper', 'eye-slash', 'face-frown', 'face-smile', 'film', 'finger-print', 'fire', 'flag', 'folder',
        'folder-arrow-down', 'folder-minus', 'folder-open', 'folder-plus', 'forward', 'funnel', 'gif', 'gift', 'gift-top',
        'globe-alt', 'globe-americas', 'globe-asia-australia', 'globe-europe-africa', 'hand-raised', 'hand-thumb-down',
        'hand-thumb-up', 'hashtag', 'heart', 'home', 'home-modern', 'identification', 'inbox', 'inbox-arrow-down', 'inbox-stack',
        'information-circle', 'key', 'language', 'lifebuoy', 'light-bulb', 'link', 'list-bullet', 'lock-closed', 'lock-open',
        'magnifying-glass', 'magnifying-glass-circle', 'magnifying-glass-minus', 'magnifying-glass-plus', 'map', 'map-pin',
        'megaphone', 'microphone', 'minus', 'minus-circle', 'minus-small', 'moon', 'musical-note', 'newspaper', 'no-symbol',
        'paint-brush', 'paper-airplane', 'paper-clip', 'pause', 'pause-circle', 'pencil', 'pencil-square', 'phone', 'phone-arrow-down-left',
        'phone-arrow-up-right', 'phone-x-mark', 'photo', 'play', 'play-circle', 'play-pause', 'plus', 'plus-circle', 'plus-small',
        'power', 'presentation-chart-bar', 'presentation-chart-line', 'printer', 'puzzle-piece', 'qr-code', 'question-mark-circle',
        'queue-list', 'radio', 'receipt-percent', 'receipt-refund', 'rectangle-group', 'rectangle-stack', 'rocket-launch',
        'rss', 'scale', 'scissors', 'server', 'server-stack', 'share', 'shield-check', 'shield-exclamation', 'shopping-bag',
        'shopping-cart', 'signal', 'signal-slash', 'sparkles', 'speaker-wave', 'speaker-x-mark', 'square-2-stack', 'square-3-stack-3d',
        'squares-2x2', 'squares-plus', 'star', 'stop', 'stop-circle', 'sun', 'swatch', 'table-cells', 'tag', 'ticket', 'trash',
        'trophy', 'truck', 'tv', 'user', 'user-circle', 'user-group', 'user-minus', 'user-plus', 'users', 'variable', 'video-camera',
        'video-camera-slash', 'view-columns', 'viewfinder-circle', 'wallet', 'wifi', 'window', 'wrench', 'wrench-screwdriver',
        'x-circle', 'x-mark'
    ];

    public function roles(): array
    {
        return \App\Models\Role::all()->toArray();
    }

    public function createRole()
    {
        $this->authorize('create', \App\Models\Role::class);

        $this->validate([
            'newRoleName' => 'required|string|max:255',
            'newRoleColor' => 'required|string|max:50',
            'newRoleDescription' => 'nullable|string|max:500',
            'newRoleIcon' => ['nullable', 'string', 'max:50', 'in:' . implode(',', $this->allowedIcons)],
        ]);

        \App\Models\Role::create([
            'name' => $this->newRoleName,
            'color' => $this->newRoleColor,
            'description' => $this->newRoleDescription,
            'icon' => $this->newRoleIcon,
        ]);

        Flux::modal('create-role-modal')->close();
        Flux::toast('Role created successfully!', 'Success', variant: 'success');
        $this->reset(['newRoleName', 'newRoleColor', 'newRoleDescription', 'newRoleIcon']);
    }

    public function openEditModal($roleId)
    {
        $role = \App\Models\Role::findOrFail($roleId);
        $this->authorize('update', $role);

        $this->updateRoleName = $role->name;
        $this->updateRoleColor = $role->color;
        $this->updateRoleDescription = $role->description;
        $this->updateRoleIcon = $role->icon;
    }

    public function updateRole()
    {
        $role = \App\Models\Role::where('name', $this->updateRoleName)->firstOrFail();
        $this->authorize('update', $role);

        $this->validate([
            'updateRoleName' => 'required|string|max:255',
            'updateRoleColor' => 'required|string|max:50',
            'updateRoleDescription' => 'nullable|string|max:500',
            'updateRoleIcon' => ['nullable', 'string', 'max:50', 'in:' . implode(',', $this->allowedIcons)],
        ]);

        $role->update([
            'color' => $this->updateRoleColor,
            'description' => $this->updateRoleDescription,
            'icon' => $this->updateRoleIcon,
        ]);

        Flux::modal('edit-role-modal')->close();
        Flux::toast('Role updated successfully!', 'Success', variant: 'success');
        $this->reset(['updateRoleName', 'updateRoleColor', 'updateRoleDescription', 'updateRoleIcon']);
    }

}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Manage Roles</flux:heading>

    <flux:modal name="create-role-modal" title="Create Role" variant="flyout" class="space-y-6">

        <flux:heading size="xl">Create New Role</flux:heading>

        <form wire:submit.prevent="createRole">
            <div class="space-y-6">
                <flux:input id="role-name" label="Role Name" wire:model.defer="newRoleName" required />
                <flux:input id="role-color" label="Color" wire:model.defer="newRoleColor" />
                <flux:input id="role-icon" label="Icon" wire:model.defer="newRoleIcon" description="Heroicons icon name" />
                <flux:textarea id="role-description" label="Description" wire:model.defer="newRoleDescription" required />

                <flux:button type="submit" variant="primary">Create Role</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit Role Modal -->
    <flux:modal name="edit-role-modal" title="Edit Role" variant="flyout" class="space-y-6">

        <flux:heading size="xl">Edit Role</flux:heading>

        <form wire:submit.prevent="updateRole">
            <div class="space-y-6">
                <flux:input id="role-name" label="Role Name" wire:model.defer="updateRoleName" required />
                <flux:input id="role-color" label="Color" wire:model.defer="updateRoleColor" />
                <flux:input id="role-icon" label="Icon" wire:model.defer="updateRoleIcon" description="Heroicons icon name" />
                <flux:textarea id="role-description" label="Description" wire:model.defer="updateRoleDescription" required />

                <flux:button type="submit" variant="primary">Update Role</flux:button>
            </div>
        </form>
    </flux:modal>

    <div class="my-6">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Color</flux:table.column>
                <flux:table.column>Description</flux:table.column>
                <flux:table.column>Icon</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->roles() as $role)
                    <flux:table.row>
                        <flux:table.cell><flux:badge color="{{ $role['color'] }}" icon="{{ $role['icon'] }}" size="sm" variant="pill">{{ $role['name'] }}</flux:badge></flux:table.cell>
                        <flux:table.cell>{{ $role['color'] }}</flux:table.cell>
                        <flux:table.cell>{{  STR::limit($role['description'], 75, '...') }}</flux:table.cell>
                        <flux:table.cell>{{ $role['icon'] }}</flux:table.cell>
                        <flux:table.cell>
                            @can('update', \App\Models\Role::class)
                                <flux:modal.trigger wire:click="openEditModal({{ $role['id'] }})" name="edit-role-modal">
                                    <flux:button size="sm" icon="pencil-square">Edit</flux:button>
                                </flux:modal.trigger>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    <!-- Create Role Modal -->
    <flux:modal.trigger name="create-role-modal" title="Create Role" variant="flyout">
        <div class="w-full text-right my-10">
            <flux:button variant="primary">Create New Role</flux:button>
        </div>
    </flux:modal.trigger>
</div>
