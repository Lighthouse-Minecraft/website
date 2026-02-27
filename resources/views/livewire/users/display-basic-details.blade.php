<?php

use Livewire\Volt\Component;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Enums\MembershipLevel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Flux\Flux;

new class extends Component {
    public User $user;
    public ?MinecraftAccount $selectedAccount = null;
    public $currentDepartment;
    public $currentDepartmentValue;
    public $currentTitle;
    public $currentRank;
    public $departments;
    public $ranks;
    public string $brigActionReason = '';
    public ?int $brigActionDays = null;
    public ?int $accountToRevoke = null;

    /**
     * Initialize component state for the given user.
     *
     * Loads the user's Minecraft accounts and populates component properties used by the UI:
     * the managed User instance, current staff department/title/rank values, and the available
     * department and rank enum cases.
     *
     * @param \App\Models\User $user The user being managed by this component.
     */
    public function mount(User $user) {
        $this->user = $user;
        $this->user->load('minecraftAccounts', 'discordAccounts');
        $this->currentDepartment = $user->staff_department?->name ?? 'None';
        $this->currentDepartmentValue = $user->staff_department?->value ?? null;
        $this->currentTitle = $user->staff_title;
        $this->currentRank = $user->staff_rank?->value;
        $this->departments = \App\Enums\StaffDepartment::cases();
        $this->ranks = \App\Enums\StaffRank::cases();
    }

    /**
     * Update the user's staff position from the component's inputs.
     *
     * Validates input, checks authorization, executes the SetUsersStaffPosition action,
     * shows a success or error toast based on the result, and closes the manage-users-staff-position modal.
     */
    public function updateStaffPosition() {
        if (!Auth::user()->can('updateStaffPosition', $this->user)) {
            Flux::toast(
                text: 'You do not have permission to update staff positions.',
                heading: 'Error',
                variant: 'danger'
            );
            return;
        }

        $department = $this->currentDepartmentValue
            ? \App\Enums\StaffDepartment::tryFrom($this->currentDepartmentValue)
            : null;

        $rank = $this->currentRank !== null
            ? \App\Enums\StaffRank::tryFrom((int) $this->currentRank)
            : null;

        $this->validate([
            'currentTitle' => ['required', 'string', 'max:255'],
            'currentDepartmentValue' => ['required', Rule::enum((\App\Enums\StaffDepartment::class))],
            'currentRank' => ['required', Rule::enum((\App\Enums\StaffRank::class))],
        ]);

        $success = \App\Actions\SetUsersStaffPosition::run(
            $this->user,
            $this->currentTitle,
            $department,
            $rank
        );

        if ($success) {
            Flux::toast(
                text: 'Staff position updated successfully.',
                heading: 'Success',
                variant: 'success'
            );
        } else {
            Flux::toast(
                text: 'Failed to update staff position. Please try again.',
                heading: 'Error',
                variant: 'danger'
            );
        }

        Flux::modal('manage-users-staff-position')->close();
    }

    /**
     * Removes the current user's staff position when the caller is authorized, then provides success or error feedback and closes the Manage Staff Position modal.
     *
     * If the caller lacks permission, an error toast is shown and no action is taken.
     */
    public function removeStaffPosition() {
        if (!Auth::user()->can('removeStaffPosition', $this->user)) {
            Flux::toast(
                text: 'You do not have permission to remove staff positions.',
                heading: 'Error',
                variant: 'danger'
            );
            return;
        }

        $success = \App\Actions\RemoveUsersStaffPosition::run($this->user);

        if ($success) {
            Flux::toast(
                text: 'Staff position removed successfully.',
                heading: 'Success',
                variant: 'success'
            );
        } else {
            Flux::toast(
                text: 'Failed to remove staff position. Please try again.',
                heading: 'Error',
                variant: 'danger'
            );
        }

        Flux::modal('manage-users-staff-position')->close();
    }

    /**
     * Loads the specified Minecraft account for the managed user into `$selectedAccount` and opens the account detail modal if the account exists.
     *
     * @param int $accountId The ID of the Minecraft account to load.
     */
    public function showAccount(int $accountId): void
    {
        $query = $this->user->minecraftAccounts()->with('user');

        if (! Auth::user()->isAdmin()) {
            $query->where('status', '!=', \App\Enums\MinecraftAccountStatus::Removed);
        }

        $this->selectedAccount = $query->find($accountId);

        if ($this->selectedAccount) {
            Flux::modal('mc-account-detail')->show();
        }
    }

    public function confirmRevoke(int $accountId): void
    {
        $account = $this->user->minecraftAccounts()->find($accountId);

        if (! $account || ! Auth::user()->can('revoke', $account)) {
            return;
        }

        $this->accountToRevoke = $accountId;
        Flux::modal('confirm-revoke-mc-account')->show();
    }

    public function revokeMinecraftAccount(): void
    {
        if (! $this->accountToRevoke) {
            Flux::modal('confirm-revoke-mc-account')->close();
            return;
        }

        $account = $this->user->minecraftAccounts()->find($this->accountToRevoke);

        if (! $account) {
            Flux::modal('confirm-revoke-mc-account')->close();
            $this->accountToRevoke = null;
            Flux::toast(text: 'Account not found. It may have already been removed.', heading: 'Error', variant: 'danger');
            return;
        }

        $this->authorize('revoke', $account);

        $result = \App\Actions\RevokeMinecraftAccount::run($account, Auth::user());

        Flux::modal('confirm-revoke-mc-account')->close();
        $this->accountToRevoke = null;

        if ($result['success']) {
            Flux::toast(text: $result['message'], heading: 'Success', variant: 'success');
            $this->user->refresh();
        } else {
            Flux::toast(text: $result['message'], heading: 'Error', variant: 'danger');
        }
    }

    public function reactivateMinecraftAccount(int $accountId): void
    {
        if (! Auth::user()->isAdmin()) {
            Flux::toast(
                text: 'You do not have permission to reactivate Minecraft accounts.',
                heading: 'Error',
                variant: 'danger'
            );
            return;
        }

        $account = $this->user->minecraftAccounts()->findOrFail($accountId);
        $result = \App\Actions\ReactivateMinecraftAccount::run($account, Auth::user());

        if ($result['success']) {
            Flux::toast(text: $result['message'], heading: 'Success', variant: 'success');
            $this->user->refresh();
        } else {
            Flux::toast(text: $result['message'], heading: 'Error', variant: 'danger');
        }
    }

    public function forceDeleteMinecraftAccount(int $accountId): void
    {
        if (! Auth::user()->isAdmin()) {
            Flux::toast(
                text: 'You do not have permission to permanently delete Minecraft accounts.',
                heading: 'Error',
                variant: 'danger'
            );
            return;
        }

        $account = $this->user->minecraftAccounts()->findOrFail($accountId);
        $result = \App\Actions\ForceDeleteMinecraftAccount::run($account, Auth::user());

        if ($result['success']) {
            Flux::toast(text: $result['message'], heading: 'Success', variant: 'success');
            $this->user->refresh();
        } else {
            Flux::toast(text: $result['message'], heading: 'Error', variant: 'danger');
        }
    }

    public function revokeDiscordAccount(int $accountId): void
    {
        if (! Auth::user()->isAdmin()) {
            Flux::toast(
                text: 'You do not have permission to revoke Discord accounts.',
                heading: 'Error',
                variant: 'danger'
            );
            return;
        }

        $account = $this->user->discordAccounts()->findOrFail($accountId);

        try {
            \App\Actions\RevokeDiscordAccount::run($account, Auth::user());
        } catch (\Exception $e) {
            Flux::toast(
                text: 'Failed to revoke Discord account. Please try again.',
                heading: 'Error',
                variant: 'danger'
            );
            return;
        }

        Flux::toast(
            text: 'Discord account revoked successfully.',
            heading: 'Success',
            variant: 'success'
        );
        $this->user->refresh();
    }

    /**
     * Promotes the component's user to the next membership level.
     *
     * Requires the current user to have the `manage-stowaway-users` permission.
     * On success the user model is refreshed, the promotion confirmation modal is closed,
     * and a success toast displaying the new membership level label is shown.
     */
    public function promoteUser(): void
    {
        if (! Auth::user()->can('manage-stowaway-users')) {
            Flux::toast(text: 'You do not have permission to promote users.', heading: 'Error', variant: 'danger');
            return;
        }

        \App\Actions\PromoteUser::run($this->user);
        $this->user->refresh();

        Flux::modal('profile-promote-confirm-modal')->close();
        Flux::toast(
            text: "Promoted to {$this->user->membership_level->label()} successfully.",
            heading: 'Promoted',
            variant: 'success'
        );
    }

    /**
     * Prepares and opens the "Put in Brig" modal for the managed user.
     *
     * Resets brigActionReason to an empty string and brigActionDays to null, then displays
     * the profile-put-in-brig-modal. No action is taken if the current user lacks the
     * 'manage-stowaway-users' permission.
     */
    public function openPutInBrigModal(): void
    {
        if (! Auth::user()->can('manage-stowaway-users')) {
            return;
        }
        $this->brigActionReason = '';
        $this->brigActionDays = null;
        Flux::modal('profile-put-in-brig-modal')->show();
    }

    /**
     * Place the component's user into the Brig with a reason and optional duration.
     *
     * Requires the current user to have the `manage-stowaway-users` permission.
     * Validates `brigActionReason` (required, at least 5 characters) and
     * `brigActionDays` (optional integer between 1 and 365). When valid, schedules
     * an expiration if days are provided, executes the brig placement, refreshes
     * the user model, closes the put-in-brig modal, and displays a success toast.
     */
    public function confirmPutInBrig(): void
    {
        if (! Auth::user()->can('manage-stowaway-users')) {
            return;
        }

        $this->validate([
            'brigActionReason' => 'required|string|min:5',
            'brigActionDays' => 'nullable|integer|min:1|max:365',
        ]);

        $expiresAt = $this->brigActionDays ? now()->addDays((int) $this->brigActionDays) : null;

        \App\Actions\PutUserInBrig::run($this->user, Auth::user(), $this->brigActionReason, $expiresAt);

        $this->user->refresh();
        Flux::modal('profile-put-in-brig-modal')->close();
        Flux::toast(text: "{$this->user->name} has been placed in the Brig.", heading: 'Done', variant: 'success');
    }

    /**
     * Prepare and open the "release from Brig" modal for the current user.
     *
     * Resets the brig action reason to an empty string and shows the
     * profile-release-from-brig-modal. If the current user does not have
     * the `manage-stowaway-users` permission, no action is taken.
     */
    public function openReleaseFromBrigModal(): void
    {
        if (! Auth::user()->can('manage-stowaway-users')) {
            return;
        }
        $this->brigActionReason = '';
        Flux::modal('profile-release-from-brig-modal')->show();
    }

    /**
     * Release the component's user from the Brig after validating a release reason.
     *
     * Requires the current user to have the `manage-stowaway-users` permission. Validates
     * that `brigActionReason` is provided and at least 5 characters long, executes the
     * release action, refreshes the user model, closes the release modal, and shows a
     * success toast announcing the release.
     */
    public function confirmReleaseFromBrig(): void
    {
        if (! Auth::user()->can('manage-stowaway-users')) {
            return;
        }

        $this->validate([
            'brigActionReason' => 'required|string|min:5',
        ]);

        \App\Actions\ReleaseUserFromBrig::run($this->user, Auth::user(), $this->brigActionReason);

        $this->user->refresh();
        Flux::modal('profile-release-from-brig-modal')->close();
        Flux::toast(text: "{$this->user->name} has been released from the Brig.", heading: 'Released', variant: 'success');
    }

    /**
     * Get the next MembershipLevel after the user's current membership level.
     *
     * @return MembershipLevel|null The next membership level, or `null` if the user is at the highest level or their current level is not found.
     */
    public function getNextMembershipLevelProperty(): ?MembershipLevel
    {
        $levels = MembershipLevel::cases();
        $currentIndex = array_search($this->user->membership_level, $levels, strict: true);
        return $levels[$currentIndex + 1] ?? null;
    }

    /**
     * Get the previous MembershipLevel before the user's current level.
     *
     * Returns null if the user is at or below Traveler (we don't demote below Traveler).
     */
    public function getPreviousMembershipLevelProperty(): ?MembershipLevel
    {
        if ($this->user->membership_level->value <= MembershipLevel::Traveler->value) {
            return null;
        }

        $levels = MembershipLevel::cases();
        $currentIndex = array_search($this->user->membership_level, $levels, strict: true);
        return $levels[$currentIndex - 1] ?? null;
    }

    public function demoteUser(): void
    {
        if (! Auth::user()->can('manage-stowaway-users')) {
            Flux::toast(text: 'You do not have permission to demote users.', heading: 'Error', variant: 'danger');
            return;
        }

        \App\Actions\DemoteUser::run($this->user);
        $this->user->refresh();

        Flux::modal('profile-demote-confirm-modal')->close();
        Flux::toast(
            text: "Demoted to {$this->user->membership_level->label()} successfully.",
            heading: 'Demoted',
            variant: 'success'
        );
    }

}; ?>

<div>
    <div class="w-full block md:flex md:gap-4">
        <flux:card class="w-full md:w-1/2 lg:w-1/3 p-6 space-y-2 mb-6 md:mb-0">
            <div class="flex items-center gap-3 flex-wrap mb-2">
                <flux:heading size="xl">{{ $user->name }}</flux:heading>
                @if($user->isInBrig())
                    <flux:badge color="red">In the Brig</flux:badge>
                @endif
            </div>
            <flux:text>Member Rank: {{ $user->membership_level->label() }}</flux:text>
            <flux:text>Joined on {{ $user->created_at->format('F j, Y') }}</flux:text>

            @can('manage-stowaway-users')
                <div class="flex flex-wrap gap-2 pt-3">
                    @if($user->isInBrig())
                        <flux:button
                            wire:click="openReleaseFromBrigModal"
                            size="sm"
                            variant="primary"
                            icon="lock-open"
                        >
                            Release from Brig
                        </flux:button>
                    @elseif(! $user->staff_department && $user->id !== Auth::id())
                        <flux:button
                            wire:click="openPutInBrigModal"
                            size="sm"
                            variant="ghost"
                            icon="lock-closed"
                            class="hover:!text-red-600 dark:hover:!text-red-400 hover:!bg-red-50 dark:hover:!bg-red-950"
                        >
                            Put in Brig
                        </flux:button>
                    @endif

                    @if(! $user->isInBrig() && $this->nextMembershipLevel !== null)
                        <flux:modal.trigger name="profile-promote-confirm-modal">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="arrow-up-circle"
                            >
                                Promote to {{ $this->nextMembershipLevel->label() }}
                            </flux:button>
                        </flux:modal.trigger>
                    @endif

                    @if(! $user->isInBrig() && $this->previousMembershipLevel !== null)
                        <flux:modal.trigger name="profile-demote-confirm-modal">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="arrow-down-circle"
                                class="hover:!text-red-600 dark:hover:!text-red-400 hover:!bg-red-50 dark:hover:!bg-red-950"
                            >
                                Demote to {{ $this->previousMembershipLevel->label() }}
                            </flux:button>
                        </flux:modal.trigger>
                    @endif
                </div>
            @endcan
        </flux:card>

        @if($user->staff_department)
            <flux:card class="w-full md:w-1/2 lg:w-1/3 mb-6 md:mb-0">
                <flux:heading size="xl">{{  $user->staff_title }}</flux:heading>
                <flux:text>Department: {{  $user->staff_department->label() }}</flux:text>
                <flux:text>Rank: {{  $user->staff_rank->label() }}</flux:text>

                @if (Auth::user()->isAdmin())
                <div class="mt-6 text-center">
                    <flux:modal.trigger name="manage-users-staff-position">
                        <flux:button>Manage Staff Position</flux:button>
                    </flux:modal.trigger>
                </div>
                @endif
            </flux:card>
        @elseif (Auth::user()->isAdmin())
            <flux:card class="w-full md:w-1/4 mb-6 md:mb-0">
                <flux:heading size="xl">Staff Config</flux:heading>
                <div class="mt-6 text-center">
                    <flux:modal.trigger name="manage-users-staff-position">
                        <flux:button>Manage Staff Position</flux:button>
                    </flux:modal.trigger>
                </div>
            </flux:card>
        @endif

        @can('viewPii', $user)
            <flux:card class="w-full md:w-1/2 lg:w-1/3 p-6 space-y-2">
                <flux:heading size="xl" class="mb-4">Contact Information</flux:heading>
                <flux:text>Email: {{ $user->email }}</flux:text>
            </flux:card>
        @endcan
    </div>

    <div class="w-full flex flex-col md:flex-row gap-4 mt-6">
        <flux:card class="w-full md:w-1/2 lg:w-1/3 p-6">
            <div class="flex items-center mb-4">
                <flux:heading size="xl">Minecraft Accounts</flux:heading>
                @if(auth()->id() === $user->id)
                    <flux:spacer />
                    <flux:link href="{{ route('settings.minecraft-accounts') }}" class="text-sm text-zinc-400 hover:text-zinc-200">Manage</flux:link>
                @endif
            </div>

            @php
                $visibleAccounts = Auth::user()->isAdmin()
                    ? $user->minecraftAccounts
                    : $user->minecraftAccounts->filter(fn ($a) => $a->status !== \App\Enums\MinecraftAccountStatus::Removed);
            @endphp
            @if($visibleAccounts->isNotEmpty())
                <div class="flex flex-col gap-2">
                    @foreach($visibleAccounts as $account)
                        <div wire:key="minecraft-account-{{ $account->id }}" class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                @if($account->avatar_url)
                                    <img src="{{ $account->avatar_url }}" alt="{{ $account->username }}" class="w-8 h-8 rounded {{ $account->status === \App\Enums\MinecraftAccountStatus::Removed ? 'grayscale opacity-75' : '' }}" />
                                @endif
                                <div>
                                    <div class="flex items-center gap-2">
                                        <button wire:click="showAccount({{ $account->id }})" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">{{ $account->username }}</button>
                                        <flux:badge color="{{ $account->status->color() }}" size="sm">{{ $account->status->label() }}</flux:badge>
                                        @if($account->is_primary)
                                            <flux:badge color="blue" size="sm">Primary</flux:badge>
                                        @endif
                                    </div>
                                    <flux:text class="text-sm text-zinc-500">{{ $account->account_type->label() }}</flux:text>
                                </div>
                            </div>
                            @if($account->status === \App\Enums\MinecraftAccountStatus::Removed)
                                <div class="flex gap-1">
                                    @can('reactivate', $account)
                                        <flux:button
                                            wire:click="reactivateMinecraftAccount({{ $account->id }})"
                                            variant="primary"
                                            size="sm"
                                            wire:confirm="Reactivate this Minecraft account for {{ $user->name }}? It will be re-whitelisted.">
                                            Reactivate
                                        </flux:button>
                                    @endcan
                                    @can('forceDelete', $account)
                                        <flux:button
                                            wire:click="forceDeleteMinecraftAccount({{ $account->id }})"
                                            variant="ghost"
                                            size="sm"
                                            class="hover:!text-red-600 dark:hover:!text-red-400 hover:!bg-red-50 dark:hover:!bg-red-950"
                                            wire:confirm="PERMANENTLY delete this Minecraft account? This will release the UUID so it can be registered by anyone. This action cannot be undone.">
                                            Delete
                                        </flux:button>
                                    @endcan
                                </div>
                            @elseif($account->status !== \App\Enums\MinecraftAccountStatus::Cancelled && $account->status !== \App\Enums\MinecraftAccountStatus::Removed)
                                @can('revoke', $account)
                                    <flux:button
                                        wire:click="confirmRevoke({{ $account->id }})"
                                        variant="ghost"
                                        size="sm"
                                        class="hover:!text-red-600 dark:hover:!text-red-400 hover:!bg-red-50 dark:hover:!bg-red-950">
                                        Revoke
                                    </flux:button>
                                @endcan
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <flux:text class="text-zinc-500 dark:text-zinc-400 text-sm">No Minecraft accounts linked.</flux:text>

                @if(auth()->id() === $user->id)
                    <div class="mt-4">
                        <flux:button :href="route('settings.minecraft-accounts')" size="sm" variant="primary">
                            Add Account
                        </flux:button>
                    </div>
                @endif
            @endif
        </flux:card>

        {{-- Discord Accounts Card --}}
        <flux:card class="w-full md:w-1/2 lg:w-1/3 p-6">
            <div class="flex items-center mb-4">
                <flux:heading size="xl">Discord Accounts</flux:heading>
                @if(auth()->id() === $user->id)
                    <flux:spacer />
                    <flux:link href="{{ route('settings.discord-account') }}" class="text-sm text-zinc-400 hover:text-zinc-200">Manage</flux:link>
                @endif
            </div>

            @if($user->discordAccounts->isNotEmpty())
                <div class="flex flex-col gap-2">
                    @foreach($user->discordAccounts as $discordAccount)
                        <div wire:key="discord-account-{{ $discordAccount->id }}" class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <img src="{{ $discordAccount->avatarUrl() }}" alt="{{ $discordAccount->username }}" class="w-8 h-8 rounded-full" />
                                <div>
                                    <span class="font-semibold">{{ $discordAccount->displayName() }}</span>
                                    <flux:text class="text-sm text-zinc-500">
                                        {{ $discordAccount->username }}
                                        <flux:badge color="{{ $discordAccount->status->color() }}" size="sm" class="ml-1">{{ $discordAccount->status->label() }}</flux:badge>
                                    </flux:text>
                                </div>
                            </div>
                            @if(Auth::user()->isAdmin())
                                <flux:button
                                    wire:click="revokeDiscordAccount({{ $discordAccount->id }})"
                                    variant="ghost"
                                    size="sm"
                                    class="hover:!text-red-600 dark:hover:!text-red-400 hover:!bg-red-50 dark:hover:!bg-red-950"
                                    wire:confirm="Are you sure you want to revoke this Discord account from {{ $user->name }}?">
                                    Revoke
                                </flux:button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <flux:text class="text-zinc-500 dark:text-zinc-400 text-sm">No Discord accounts linked.</flux:text>
            @endif
        </flux:card>
    </div>

    <x-minecraft.mc-account-detail-modal :account="$selectedAccount" />

    <flux:modal name="manage-users-staff-position" class="w-full md:w-1/2 xl:w-1/3">
        <div class="space-y-6">
            <flux:heading size="lg" class="mb-6">Manage Staff Position</flux:heading>

            <flux:input wire:model="currentTitle" label="Title" />

            <flux:radio.group wire:model="currentDepartmentValue" variant="pills" label="Department">
                @foreach ($departments as $department)
                    @if ($currentDepartmentValue == $department->name)
                        <flux:radio value="{{ $department->value }}" :label="$department->label()" checked />
                    @else
                        <flux:radio value="{{ $department->value }}" :label="$department->label()" />
                    @endif
                @endforeach
            </flux:radio.group>

            <flux:radio.group wire:model="currentRank" variant="buttons" size="sm" label="Rank">
                @foreach ($ranks as $rank)
                    @if ($currentRank == $rank->value)
                        <flux:radio value="{{  $rank->value }}" :label="$rank->label()" checked />
                    @else
                        <flux:radio value="{{  $rank->value }}" :label="$rank->label()" />
                    @endif
                @endforeach
            </flux:radio.group>

            <div class="w-full flex">
                @if ($user->staff_department)
                    <flux:button wire:click="removeStaffPosition" variant="ghost" class="hover:!text-red-600 dark:hover:!text-red-400 hover:!bg-red-50 dark:hover:!bg-red-950">Remove Staff Position</flux:button>
                @endif
                <flux:spacer />
                <flux:button wire:click="updateStaffPosition" variant="primary">Save Changes</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Put in Brig Modal -->
    <flux:modal name="profile-put-in-brig-modal" class="w-full md:w-1/2 xl:w-1/3">
        <div class="space-y-6">
            <flux:heading size="lg">Put {{ $user->name }} in the Brig</flux:heading>
            <flux:text variant="subtle">This will suspend their community access and ban their Minecraft accounts. They will be notified.</flux:text>

            <flux:field>
                <flux:label>Reason <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model.live="brigActionReason" rows="4" placeholder="Enter reason..." />
                <flux:error name="brigActionReason" />
            </flux:field>

            <flux:field>
                <flux:label>Days Until Auto-Release</flux:label>
                <flux:description>Optional. Leave blank for no auto-release timer.</flux:description>
                <flux:input wire:model.live="brigActionDays" type="number" min="1" max="365" placeholder="e.g. 7 (leave blank for no timer)" />
                <flux:error name="brigActionDays" />
            </flux:field>

            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" x-on:click="$flux.modal('profile-put-in-brig-modal').close()">Cancel</flux:button>
                <flux:button wire:click="confirmPutInBrig" variant="danger">Confirm — Put in Brig</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Promote Confirmation Modal -->
    @if($this->nextMembershipLevel !== null)
    <flux:modal name="profile-promote-confirm-modal" class="w-full md:w-1/2 xl:w-1/3">
        <div class="space-y-6">
            <flux:heading size="lg">Confirm Promotion</flux:heading>
            <flux:text>Are you sure you want to promote <strong>{{ $user->name }}</strong> from <strong>{{ $user->membership_level->label() }}</strong> to <strong>{{ $this->nextMembershipLevel->label() }}</strong>?</flux:text>
            <flux:text variant="subtle">This will send a promotion notification to the user and sync any applicable Minecraft ranks.</flux:text>
            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" x-on:click="$flux.modal('profile-promote-confirm-modal').close()">Cancel</flux:button>
                <flux:button wire:click="promoteUser" variant="primary" icon="arrow-up-circle">Confirm Promotion</flux:button>
            </div>
        </div>
    </flux:modal>
    @endif

    <!-- Demote Confirmation Modal -->
    @if($this->previousMembershipLevel !== null)
    <flux:modal name="profile-demote-confirm-modal" class="w-full md:w-1/2 xl:w-1/3">
        <div class="space-y-6">
            <flux:heading size="lg">Confirm Demotion</flux:heading>
            <flux:text>Are you sure you want to demote <strong>{{ $user->name }}</strong> from <strong>{{ $user->membership_level->label() }}</strong> to <strong>{{ $this->previousMembershipLevel->label() }}</strong>?</flux:text>
            <flux:text variant="subtle">This will sync any applicable Minecraft and Discord ranks.</flux:text>
            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" x-on:click="$flux.modal('profile-demote-confirm-modal').close()">Cancel</flux:button>
                <flux:button wire:click="demoteUser" variant="danger" icon="arrow-down-circle">Confirm Demotion</flux:button>
            </div>
        </div>
    </flux:modal>
    @endif

    <!-- Release from Brig Modal -->
    <flux:modal name="profile-release-from-brig-modal" class="w-full md:w-1/2 xl:w-1/3">
        <div class="space-y-6">
            <flux:heading size="lg">Release {{ $user->name }} from the Brig</flux:heading>
            <flux:text variant="subtle">This will restore their community access and re-whitelist their Minecraft accounts.</flux:text>

            <flux:field>
                <flux:label>Reason for Release <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model.live="brigActionReason" rows="4" placeholder="Enter reason for release..." />
                <flux:error name="brigActionReason" />
            </flux:field>

            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" x-on:click="$flux.modal('profile-release-from-brig-modal').close()">Cancel</flux:button>
                <flux:button wire:click="confirmReleaseFromBrig" variant="primary">Confirm — Release from Brig</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Revoke Minecraft account confirmation modal --}}
    <flux:modal name="confirm-revoke-mc-account" class="min-w-[22rem] space-y-6">
        <div>
            <flux:heading size="lg">Revoke Minecraft Account</flux:heading>
            <flux:text class="mt-2">Are you sure you want to revoke this Minecraft account? The player will be removed from the server whitelist.</flux:text>
        </div>

        <div class="flex gap-2 justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="revokeMinecraftAccount">Revoke Account</flux:button>
        </div>
    </flux:modal>
</div>