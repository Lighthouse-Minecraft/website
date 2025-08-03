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

    public function roles(): array
    {
        return \App\Models\Role::all()->toArray();
    }

    public function createRole()
    {
        $this->validate([
            'newRoleName' => 'required|string|max:255',
            'newRoleColor' => 'required|string|max:50',
            'newRoleDescription' => 'nullable|string|max:500',
            'newRoleIcon' => 'nullable|string|max:50',
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
        $this->updateRoleName = $role->name;
        $this->updateRoleColor = $role->color;
        $this->updateRoleDescription = $role->description;
        $this->updateRoleIcon = $role->icon;
    }


}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Manage Roles</flux:heading>

    <!-- Create Role Modal -->
    <flux:modal.trigger name="create-role-modal" title="Create Role" variant="flyout">
        <flux:button variant="primary">Create New Role</flux:button>
    </flux:modal.trigger>

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
                            <flux:modal.trigger wire:click="openEditModal({{ $role['id'] }})" name="edit-role-modal">
                                <flux:button size="sm" icon="pencil-square">Edit</flux:button>
                            </flux:modal.trigger>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
</div>
