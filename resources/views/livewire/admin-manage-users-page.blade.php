<?php

use App\Models\User;
use App\Models\Role;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Validator;
use \Livewire\WithPagination;
use Flux\Flux;


new class extends Component {
    public $sortBy = 'name';
    public $sortDirection = 'desc';
    public $perPage = 10;
    public $editUserId = null;
    public $editUserData = [
        'name' => '',
        'email' => '',
    ];
    public array $editUserRoles = [];
    public $allRoles;

    public function mount()
    {
        $this->allRoles = Role::all();
    }

    public function openEditModal($userId)
    {
        $user = User::findOrFail($userId);
        $this->editUserId = $user->id;
        $this->editUserData = [
            'name' => $user->name,
            'email' => $user->email,
        ];
        $this->editUserRoles = $user->roles->pluck('id')->toArray();
    }

    public function sort($column) {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    #[\Livewire\Attributes\Computed]
    public function users()
    {
        return \App\Models\User::query()
            ->tap(fn ($query) => $this->sortBy ? $query->orderBy($this->sortBy, $this->sortDirection) : $query)
            ->paginate(5);
    }

    public function roles()
    {
        return \App\Models\Role::all();
    }

    public function editUser($userId)
    {
        $user = User::findOrFail($userId);
        $this->authorize('update', $user);

        $this->editUserId = $userId;
        $this->editUserData = $user->only(['name', 'email']);
    }

    public function saveUser()
    {
        Validator::make([
            'name' => $this->editUserData['name'],
            'email' => $this->editUserData['email'],
            'editUserRoles' => $this->editUserRoles,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'editUserRoles.*' => 'exists:roles,id',
        ])->validate();

        $user = User::with('roles')->findOrFail($this->editUserId);

        // Prevent non-admins from adding/removing the Admin role
        $adminRoleId = \App\Models\Role::where('name', 'Admin')->value('id');
        $currentUser = auth()->user();

        if (!$currentUser->isAdmin()) {
            // Ensure the Admin role cannot be added or removed
            $hasAdminRole = $user->roles->contains('id', $adminRoleId);

            // If the user currently has the Admin role, keep it
            if ($hasAdminRole && !in_array($adminRoleId, $this->editUserRoles)) {
                $this->editUserRoles[] = $adminRoleId;
            }

            // If the user does not have the Admin role, prevent it from being added
            if (!$hasAdminRole && in_array($adminRoleId, $this->editUserRoles)) {
                $this->editUserRoles = array_diff($this->editUserRoles, [$adminRoleId]);
            }
        }

        $user->update($this->editUserData);
        $user->roles()->sync($this->editUserRoles);

        $this->editUserId = null;
        Flux::modal('edit-user-modal')->close();
        Flux::toast('User updated successfully!', 'Success', variant: 'success');
    }
};
?>

<div class="space-y-6">
    <flux:heading size="xl">Manage Users</flux:heading>

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">Email</flux:table.column>
            <flux:table.column>Roles</flux:table.column>
            {{-- <flux:table.column sortable :sorted="$sortBy === 'date'" :direction="$sortDirection" wire:click="sort('date')">Date</flux:table.column> --}}
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell class="flex items-center gap-3">
                        {{-- <flux:avatar size="xs" src="{{ $user->avatar }}" /> --}}
                        {{ $user->name }}
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">{{ $user->email }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        @foreach ($user->roles as $role)
                            <flux:badge size="xs" color="{{ $role->color }}" icon="{{  $role->icon }}" variant="pill">{{ $role->name }}</flux:badge>
                        @endforeach
                    </flux:table.cell>

                    <flux:table.cell>
                        @can('update', $user)
                            <flux:modal.trigger name="edit-user-modal" wire:click="openEditModal({{ $user->id }})">
                                <flux:button size="xs" icon="pencil-square"></flux:button>
                            </flux:modal.trigger>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>

            @endforeach
        </flux:table.rows>
    </flux:table>

    <!-- Edit User Modal -->
    <flux:modal name="edit-user-modal" title="Edit User" variant="flyout">
        <div class="space-y-6">
            <flux:heading size="xl">Edit User</flux:heading>
                <form wire:submit.prevent="saveUser">
                    <div class="space-y-6">
                        <flux:input label="Name" wire:model.defer="editUserData.name" required />
                        <flux:input label="Email" type="email" wire:model.defer="editUserData.email" required />

                        <flux:checkbox.group wire:model.defer="editUserRoles">
                            @foreach($allRoles as $role)
                                @if ($role->name != 'Guest')
                                    <flux:checkbox value="{{ $role->id }}" label="{{ $role->name }}" />
                                @endif
                            @endforeach
                        </flux:checkbox.group>

                        <div class="flex">
                            <flux:spacer />
                            <flux:button type="submit" variant="primary">Save</flux:button>
                        </div>
                    </div>
                </form>
        </div>
    </flux:modal>
</div>
