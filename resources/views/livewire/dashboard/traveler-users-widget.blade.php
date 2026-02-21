<?php

use App\Enums\MembershipLevel;
use App\Models\User;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public $selectedUser = null;
    public $showUserModal = false;

    /**
     * Retrieve Traveler members who are not in brig, ordered by their promotion time ascending.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> Collection of Traveler users not in brig ordered by `promoted_at` ascending.
     */
    public function getTravelerUsersProperty()
    {
        return User::where('membership_level', MembershipLevel::Traveler->value)
            ->where('in_brig', false)
            ->orderBy('promoted_at', 'asc')
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

    public function promoteToResident()
    {
        if (!$this->selectedUser) {
            return;
        }

        $this->authorize('manage-traveler-users');

        try {
            \App\Actions\PromoteUser::run($this->selectedUser, MembershipLevel::Resident);

            Flux::toast("Successfully promoted {$this->selectedUser->name} to Resident!", 'Success', variant: 'success');

            // Close modal and refresh the component
            $this->closeModal();
            $this->dispatch('$refresh');

        } catch (\Exception $e) {
            Flux::toast('Failed to promote user. Please try again.', 'Error', variant: 'danger');
        }
    }
}; ?>

<flux:card class="w-full">
    <flux:heading size="md" class="mb-4">Traveler Users</flux:heading>
    <flux:text variant="subtle">Traveler users are new members who are just starting out with limited access.</flux:text>

    @if($this->travelerUsers->count() > 0)
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Time as Traveler</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->travelerUsers as $user)
                    <flux:table.row>
                        <flux:table.cell>
                            <flux:link href="{{ route('profile.show', $user) }}">{{ $user->name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->promoted_at?->diffForHumans() ?? 'N/A' }}</flux:table.cell>
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
        <flux:text class="text-center py-4 text-zinc-500">No Traveler users</flux:text>
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

                    @if($selectedUser->promoted_at)
                        <div>
                            <flux:text class="font-medium">Promoted to Traveler:</flux:text>
                            <flux:text>{{ $selectedUser->promoted_at->format('F j, Y \a\t g:i A') }}</flux:text>
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

                    @can('manage-traveler-users')
                        <flux:button
                            wire:click="promoteToResident"
                            variant="primary"
                        >
                            Promote to Resident
                        </flux:button>
                    @endcan
                </div>
            </div>
        </flux:modal>
    @endif
</flux:card>