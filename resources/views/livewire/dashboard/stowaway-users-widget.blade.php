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
    public $promotedUserName = null;
    public $promotedUserUrl = null;

    /**
     * Fetches Stowaway users who are not in the Brig, ordered by name.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> Collection of User models for stowaway users not in Brig, ordered by name.
     */
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

    /**
     * Reset the user modal state and clear selected user and brig fields.
     *
     * Clears the currently selected user, hides the user details modal, and resets
     * brigReason to an empty string and brigDays to null.
     *
     * @return void
     */
    public function closeModal()
    {
        $this->selectedUser = null;
        $this->showUserModal = false;
        $this->brigReason = '';
        $this->brigDays = null;
    }

    /**
     * Promote the currently selected stowaway user to the Traveler membership level.
     *
     * If no user is selected this method does nothing. On success it shows a success
     * toast, closes any open user modal, and refreshes the component; on failure it
     * shows an error toast.
     *
     * @return void
     */
    public function promoteToTraveler()
    {
        if (!$this->selectedUser) {
            return;
        }

        $this->authorize('manage-stowaway-users');

        try {
            \App\Actions\PromoteUser::run($this->selectedUser, MembershipLevel::Traveler);

            $this->promotedUserName = $this->selectedUser->name;
            $this->promotedUserUrl = route('profile.show', $this->selectedUser);

            // Close modal and refresh the component
            $this->closeModal();

        } catch (\Exception $e) {
            Flux::toast('Failed to promote user. Please try again.', 'Error', variant: 'danger');
        }
    }

    /**
     * Open the Brig reason modal and initialize brig input fields.
     *
     * Ensures the caller is authorized to manage stowaway users, resets the
     * brigReason and brigDays properties, and displays the "brig-reason-modal".
     */
    public function openBrigModal()
    {
        $this->authorize('manage-stowaway-users');
        $this->brigReason = '';
        $this->brigDays = null;
        Flux::modal('brig-reason-modal')->show();
    }

    /**
     * Place the currently selected stowaway user in the Brig after validating input and authorization.
     *
     * If no user is selected this method returns immediately. It requires the caller to be authorized
     * for 'manage-stowaway-users' and validates that `brigReason` is at least 5 characters and that
     * `brigDays`, if provided, is an integer between 1 and 365. If `brigDays` is provided an expiration
     * datetime is computed; otherwise the Brig placement is indefinite.
     *
     * On success this runs the Brig placement action, shows a success toast, closes the brig and user
     * modals, and triggers a component refresh. On failure it shows an error toast.
     *
     * @return void
     */
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

        $expiresAt = $this->brigDays ? now()->addDays((int) $this->brigDays) : null;

        try {
            \App\Actions\PutUserInBrig::run(
                target: $this->selectedUser,
                admin: Auth::user(),
                reason: $this->brigReason,
                expiresAt: $expiresAt
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

    @if($promotedUserName)
        <div class="mt-4 flex items-center justify-between gap-2 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-700 dark:bg-green-900/30 dark:text-green-300">
            <span>
                Successfully promoted
                <a href="{{ $promotedUserUrl }}" class="font-semibold underline hover:no-underline">{{ $promotedUserName }}</a>
                to Traveler!
            </span>
            <button wire:click="$set('promotedUserName', null)" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200">&times;</button>
        </div>
    @endif

    @if($this->stowawayUsers->count() > 0)
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Joined</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->stowawayUsers as $user)
                    <flux:table.row wire:key="stowaway-user-{{ $user->id }}">
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
        <flux:modal wire:model="showUserModal" name="user-details-modal" class="max-w-lg">
            <div class="space-y-4">
                {{-- Header: avatar + name + badge --}}
                <div class="flex items-center gap-4">
                    @if($selectedUser->avatarUrl())
                        <img src="{{ $selectedUser->avatarUrl() }}"
                             alt="{{ $selectedUser->name }}"
                             class="w-16 h-16 rounded" />
                    @else
                        <flux:avatar name="{{ $selectedUser->name }}" size="xl" />
                    @endif

                    <div>
                        <flux:heading size="xl">
                            <flux:link href="{{ route('profile.show', $selectedUser) }}">{{ $selectedUser->name }}</flux:link>
                        </flux:heading>
                        <div class="flex flex-wrap gap-2 mt-1">
                            <flux:badge size="sm" color="yellow">{{ $selectedUser->membership_level->label() }}</flux:badge>
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

                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500 dark:text-zinc-400 font-medium shrink-0">Date of Birth</dt>
                        <dd>{{ $selectedUser->date_of_birth?->format('M j, Y') ?? 'Not set' }}</dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500 dark:text-zinc-400 font-medium shrink-0">Age</dt>
                        <dd>{{ $selectedUser->age() ?? 'Unknown' }}</dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500 dark:text-zinc-400 font-medium shrink-0">Parent Email</dt>
                        <dd>{{ $selectedUser->parent_email ?? 'None' }}</dd>
                    </div>

                    @if($selectedUser->rules_accepted_at)
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500 dark:text-zinc-400 font-medium shrink-0">Rules Accepted</dt>
                            <dd>{{ $selectedUser->rules_accepted_at->format('M j, Y g:i A') }}</dd>
                        </div>
                    @endif
                </dl>

                <flux:separator />

                <div class="flex items-center gap-2 justify-between">
                    <flux:button
                        href="/tickets/create-admin?user_id={{ $selectedUser->id }}"
                        variant="ghost"
                        icon="inbox"
                    >
                        Create Ticket
                    </flux:button>

                    @can('manage-stowaway-users')
                        <div class="flex gap-2">
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
                        </div>
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
                <flux:label>Brig Duration (Days)</flux:label>
                <flux:description>Optional. Leave blank for no expiry timer. Enter a number of days until the brig expires.</flux:description>
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