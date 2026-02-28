<?php

use App\Enums\MembershipLevel;
use App\Models\User;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public $selectedUser = null;
    public $showUserModal = false;
    public $promotedUserName = null;
    public $promotedUserUrl = null;

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

            $this->promotedUserName = $this->selectedUser->name;
            $this->promotedUserUrl = route('profile.show', $this->selectedUser);

            // Close modal and refresh the component
            $this->closeModal();

        } catch (\Exception $e) {
            Flux::toast('Failed to promote user. Please try again.', 'Error', variant: 'danger');
        }
    }
}; ?>

<flux:card class="w-full">
    <flux:heading size="md" class="mb-4">Traveler Users</flux:heading>
    <flux:text variant="subtle">Traveler users are new members who are just starting out with limited access.</flux:text>

    @if($promotedUserName)
        <div class="mt-4 flex items-center justify-between gap-2 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-700 dark:bg-green-900/30 dark:text-green-300">
            <span>
                Successfully promoted
                <a href="{{ $promotedUserUrl }}" class="font-semibold underline hover:no-underline">{{ $promotedUserName }}</a>
                to Resident!
            </span>
            <button wire:click="$set('promotedUserName', null)" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200">&times;</button>
        </div>
    @endif

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
        <flux:modal wire:model="showUserModal" name="user-details-modal" class="max-w-lg">
            <div class="space-y-4">
                {{-- Header: avatar + name + badge --}}
                <div class="flex items-center gap-4">
                    <img src="{{ $selectedUser->gravatarUrl() }}"
                         alt="{{ $selectedUser->name }}"
                         class="w-16 h-16 rounded" />

                    <div>
                        <flux:heading size="xl">
                            <flux:link href="{{ route('profile.show', $selectedUser) }}">{{ $selectedUser->name }}</flux:link>
                        </flux:heading>
                        <div class="flex flex-wrap gap-2 mt-1">
                            <flux:badge size="sm" color="sky">{{ $selectedUser->membership_level->label() }}</flux:badge>
                        </div>
                    </div>
                </div>

                <flux:separator />

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500 dark:text-zinc-400 font-medium shrink-0">Email</dt>
                        <dd>{{ $selectedUser->email }}</dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500 dark:text-zinc-400 font-medium shrink-0">Joined</dt>
                        <dd>{{ $selectedUser->created_at->format('M j, Y g:i A') }}</dd>
                    </div>

                    @if($selectedUser->promoted_at)
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500 dark:text-zinc-400 font-medium shrink-0">Promoted to Traveler</dt>
                            <dd>{{ $selectedUser->promoted_at->format('M j, Y g:i A') }}</dd>
                        </div>
                    @endif

                </dl>

                <flux:separator />

                <div class="flex gap-2 justify-end">
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