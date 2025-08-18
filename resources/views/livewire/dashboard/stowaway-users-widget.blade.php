<?php

use App\Enums\MembershipLevel;
use App\Models\User;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public $selectedUser = null;
    public $showUserModal = false;

    public function getStowawayUsersProperty()
    {
        return User::where('membership_level', MembershipLevel::Stowaway->value)
            ->orderBy('name')
            ->get();
    }

    public function viewUser($userId)
    {
        $this->selectedUser = User::findOrFail($userId);
        $this->showUserModal = true;
    }

    public function closeModal()
    {
        $this->selectedUser = null;
        $this->showUserModal = false;
    }

    public function promoteToTraveler()
    {
        if (!$this->selectedUser) {
            return;
        }

        $this->authorize('manage-stowaway-users');

        try {
            \App\Actions\PromoteUser::run($this->selectedUser, MembershipLevel::Traveler);

            Flux::toast("Successfully promoted {$this->selectedUser->name} to Traveler!", 'Success', variant: 'success');

            // Close modal and refresh the component
            $this->closeModal();
            $this->dispatch('$refresh');

        } catch (\Exception $e) {
            Flux::toast('Failed to promote user. Please try again.', 'Error', variant: 'danger');
        }
    }
}; ?>

<flux:card class="w-full">
    <flux:heading size="md" class="mb-4">Stowaway Users</flux:heading>
    <flux:text variant="subtle">Stowaway users are those who have agreed to the Lighthouse Rules and are awaiting promotion to Traveler.</flux:text>

    @if($this->stowawayUsers->count() > 0)
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Joined</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->stowawayUsers as $user)
                    <flux:table.row>
                        <flux:table.cell>{{ $user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $user->created_at->diffForHumans() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                wire:click="viewUser({{ $user->id }})"
                                size="sm"
                                icon="magnifying-glass"
                            >
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @else
        <flux:text class="text-center py-4 text-zinc-500">No Stowaway users found.</flux:text>
    @endif

    <!-- User Details Modal -->
    @if($showUserModal && $selectedUser)
        <flux:modal wire:model="showUserModal" name="user-details-modal" class="w-full lg:w-1/2">
            <div class="space-y-6">
                <flux:heading size="lg">User Details</flux:heading>

                <div class="space-y-4">
                    <div>
                        <flux:text class="font-medium">Name:</flux:text>
                        <flux:text>{{ $selectedUser->name }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="font-medium">Email:</flux:text>
                        <flux:text>{{ $selectedUser->email }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="font-medium">Membership Level:</flux:text>
                        <flux:text>{{ $selectedUser->membership_level->label() }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="font-medium">Joined:</flux:text>
                        <flux:text>{{ $selectedUser->created_at->format('F j, Y \a\t g:i A') }}</flux:text>
                    </div>

                    @if($selectedUser->rules_accepted_at)
                        <div>
                            <flux:text class="font-medium">Rules Accepted:</flux:text>
                            <flux:text>{{ $selectedUser->rules_accepted_at->format('F j, Y \a\t g:i A') }}</flux:text>
                        </div>
                    @endif
                </div>

                <div class="flex gap-2 pt-4">
                    <flux:spacer />

                    <flux:button
                        wire:click="closeModal"
                        variant="ghost"
                    >
                        Cancel
                    </flux:button>

                    @can('manage-stowaway-users')
                        <flux:button
                            wire:click="promoteToTraveler"
                            variant="primary"
                        >
                            Promote to Traveler
                        </flux:button>
                    @endcan
                </div>
            </div>
        </flux:modal>
    @endif
</flux:card>
