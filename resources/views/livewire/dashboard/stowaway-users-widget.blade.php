<?php

use App\Enums\MembershipLevel;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public $selectedUser = null;
    public $showUserModal = false;
    public $brigReason = '';
    public $brigDays = null;

    public function getStowawayUsersProperty()
    {
        return User::where('membership_level', MembershipLevel::Stowaway->value)
            ->where('in_brig', false)
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
        $this->brigReason = '';
        $this->brigDays = null;
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

    public function openBrigModal()
    {
        $this->authorize('manage-stowaway-users');
        $this->brigReason = '';
        $this->brigDays = null;
        Flux::modal('brig-reason-modal')->show();
    }

    public function confirmPutInBrig()
    {
        if (!$this->selectedUser) {
            return;
        }

        $this->authorize('manage-stowaway-users');

        $this->validate([
            'brigReason' => 'required|string|min:5',
            'brigDays' => 'nullable|integer|min:1|max:365',
        ]);

        $appealAvailableAt = $this->brigDays ? now()->addDays((int) $this->brigDays) : null;

        try {
            \App\Actions\PutUserInBrig::run(
                target: $this->selectedUser,
                admin: Auth::user(),
                reason: $this->brigReason,
                appealAvailableAt: $appealAvailableAt
            );

            Flux::toast("{$this->selectedUser->name} has been placed in the Brig.", 'Done', variant: 'success');

            Flux::modal('brig-reason-modal')->close();
            $this->closeModal();
            $this->dispatch('$refresh');

        } catch (\Exception $e) {
            Flux::toast('Failed to put user in the Brig. Please try again.', 'Error', variant: 'danger');
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
                        <flux:table.cell>
                            <flux:link href="{{ route('profile.show', $user) }}">{{ $user->name }}</flux:link>
                        </flux:table.cell>
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
                        <a href="{{ route('profile.show', $selectedUser) }}" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                            {{ $selectedUser->name }}
                        </a>
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
                    <flux:button
                        href="/tickets/create-admin?user_id={{ $selectedUser->id }}"
                        variant="ghost"
                        icon="inbox"
                        title="Create Admin Ticket"
                    >
                        Create Ticket
                    </flux:button>

                    <flux:spacer />

                    <flux:button
                        wire:click="closeModal"
                        variant="ghost"
                    >
                        Cancel
                    </flux:button>

                    @can('manage-stowaway-users')
                        <flux:button
                            wire:click="openBrigModal"
                            variant="danger"
                            icon="lock-closed"
                        >
                            Put in Brig
                        </flux:button>

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

    <!-- Put in Brig Reason Modal -->
    <flux:modal name="brig-reason-modal" class="w-full lg:w-1/2">
        <div class="space-y-6">
            <flux:heading size="lg">Put {{ $selectedUser?->name }} in the Brig</flux:heading>
            <flux:text variant="subtle">Placing this user in the Brig will suspend their community access and ban their Minecraft accounts. They will be notified.</flux:text>

            <flux:field>
                <flux:label>Reason <span class="text-red-500">*</span></flux:label>
                <flux:description>Explain why this user is being placed in the Brig.</flux:description>
                <flux:textarea wire:model.live="brigReason" rows="4" placeholder="Enter reason..." />
                <flux:error name="brigReason" />
            </flux:field>

            <flux:field>
                <flux:label>Days Until Appeal Available</flux:label>
                <flux:description>Optional. Leave blank to allow appeal immediately. Enter a number of days to delay the appeal window.</flux:description>
                <flux:input wire:model.live="brigDays" type="number" min="1" max="365" placeholder="e.g. 7 (leave blank for no timer)" />
                <flux:error name="brigDays" />
            </flux:field>

            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" x-on:click="$flux.modal('brig-reason-modal').close()">Cancel</flux:button>
                <flux:button wire:click="confirmPutInBrig" variant="danger">Confirm — Put in Brig</flux:button>
            </div>
        </div>
    </flux:modal>
</flux:card>
