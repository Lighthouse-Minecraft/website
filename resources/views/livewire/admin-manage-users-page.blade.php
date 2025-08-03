<?php

use App\Models\User;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Validator;
use \Livewire\WithPagination;
use Flux\Flux;


new class extends Component {
    public $sortBy = 'date';
    public $sortDirection = 'desc';
    public $perPage = 10;
    public $editUserId = null;
    public $editUserData = [
        'name' => '',
        'email' => '',
    ];

    public function openEditModal($userId)
    {
        $user = User::findOrFail($userId);
        $this->editUserId = $user->id;
        $this->editUserData = [
            'name' => $user->name,
            'email' => $user->email,
        ];
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

    public function editUser($userId)
    {
        $user = User::findOrFail($userId);
        $this->editUserId = $userId;
        $this->editUserData = $user->only(['name', 'email']);
        $this->dispatch('open-modal', 'edit-user-modal');
    }

    public function saveUser()
    {
        Validator::make($this->editUserData, [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ])->validate();

        User::findOrFail($this->editUserId)->update($this->editUserData);
        $this->dispatch('close-modal', 'edit-user-modal');
        $this->editUserId = null;
        Flux::modal('edit-user-modal')->close();
        Flux::toast('User updated successfully!', 'Success', variant: 'success');
    }
};
?>

<div class="p-4 max-w-5xl mx-auto w-full">
    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">Email</flux:table.column>
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


                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>

                            <flux:menu>
                                <flux:modal.trigger name="edit-user-modal" wire:click="openEditModal({{ $user->id }})">
                                    <flux:menu.item>Edit User</flux:menu.item>
                                </flux:modal.trigger>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>

            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="edit-user-modal" title="Edit User" variant="flyout">
        <div class="space-y-6">
            <flux:heading size="xl">Edit User</flux:heading>
                <form wire:submit.prevent="saveUser">
                    <div class="space-y-6">
                        <flux:input label="Name" wire:model.defer="editUserData.name" required />
                        <flux:input label="Email" type="email" wire:model.defer="editUserData.email" required />

                        <div class="flex">
                            <flux:spacer />
                            <flux:button type="submit" variant="primary">Save</flux:button>
                        </div>
                    </div>
                </form>
        </div>
    </flux:modal>
</div>
