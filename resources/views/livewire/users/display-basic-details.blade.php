<?php

use Livewire\Volt\Component;
use App\Models\MinecraftAccount;
use App\Models\StaffPosition;
use App\Models\User;
use App\Enums\MembershipLevel;
use App\Enums\StaffRank;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Flux\Flux;

new class extends Component {
    public User $user;
    public ?MinecraftAccount $selectedAccount = null;
    public string $brigActionReason = '';
    public ?int $brigActionDays = null;
    public ?int $accountToRevoke = null;
    public ?int $accountToForceDelete = null;

    public bool $editingUser = false;
    public array $editUserData = [
        'name' => '',
        'email' => '',
        'date_of_birth' => '',
        'parent_email' => '',
    ];

    public function mount(User $user) {
        $this->user = $user;
        $this->user->load('minecraftAccounts', 'discordAccounts', 'parents', 'children', 'staffPosition');
    }

    public function assignToPosition(int $positionId): void
    {
        $position = StaffPosition::findOrFail($positionId);
        $this->authorize('assign', $position);

        \App\Actions\AssignStaffPosition::run($position, $this->user);

        $this->user->refresh();
        $this->user->load('staffPosition');
        Flux::modal('assign-staff-position')->close();
        Flux::toast(text: "Assigned to {$position->title}.", heading: 'Assigned', variant: 'success');
    }

    public function removeFromPosition(): void
    {
        $position = $this->user->staffPosition;

        if (! $position) {
            Flux::toast(text: 'User does not have a staff position.', heading: 'Error', variant: 'danger');
            return;
        }

        $this->authorize('assign', $position);

        \App\Actions\UnassignStaffPosition::run($position);

        $this->user->refresh();
        $this->user->load('staffPosition');
        Flux::toast(text: 'Staff position removed.', heading: 'Removed', variant: 'success');
    }

    public function getAvailablePositionsProperty()
    {
        if (! Auth::user()?->can('viewAny', StaffPosition::class)) {
            return collect();
        }

        return StaffPosition::vacant()->ordered()->get();
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
        $account = $this->user->minecraftAccounts()->findOrFail($accountId);
        $this->authorize('reactivate', $account);

        $result = \App\Actions\ReactivateMinecraftAccount::run($account, Auth::user());

        if ($result['success']) {
            Flux::toast(text: $result['message'], heading: 'Success', variant: 'success');
            $this->user->refresh();
        } else {
            Flux::toast(text: $result['message'], heading: 'Error', variant: 'danger');
        }
    }

    public function confirmForceDelete(int $accountId): void
    {
        $account = $this->user->minecraftAccounts()->find($accountId);

        if (! $account || ! Auth::user()->can('forceDelete', $account)) {
            return;
        }

        $this->accountToForceDelete = $accountId;
        Flux::modal('confirm-force-delete-mc-account')->show();
    }

    public function forceDeleteMinecraftAccount(): void
    {
        if (! $this->accountToForceDelete) {
            Flux::modal('confirm-force-delete-mc-account')->close();
            return;
        }

        $account = $this->user->minecraftAccounts()->find($this->accountToForceDelete);

        if (! $account) {
            Flux::modal('confirm-force-delete-mc-account')->close();
            $this->accountToForceDelete = null;
            Flux::toast(text: 'Account not found. It may have already been deleted.', heading: 'Error', variant: 'danger');
            return;
        }

        $this->authorize('forceDelete', $account);

        $result = \App\Actions\ForceDeleteMinecraftAccount::run($account, Auth::user());

        Flux::modal('confirm-force-delete-mc-account')->close();
        $this->accountToForceDelete = null;

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

        if ($this->user->membership_level->value < MembershipLevel::Stowaway->value) {
            Flux::toast(text: 'This user cannot be promoted from this page.', heading: 'Error', variant: 'danger');
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
     * 'put-in-brig' permission.
     */
    public function openPutInBrigModal(): void
    {
        if (! Auth::user()->can('put-in-brig')) {
            return;
        }
        $this->brigActionReason = '';
        $this->brigActionDays = null;
        Flux::modal('profile-put-in-brig-modal')->show();
    }

    /**
     * Place the component's user into the Brig with a reason and optional duration.
     *
     * Requires the current user to have the `put-in-brig` permission.
     * Validates `brigActionReason` (required, at least 5 characters) and
     * `brigActionDays` (optional integer between 1 and 365). When valid, schedules
     * an expiration if days are provided, executes the brig placement, refreshes
     * the user model, closes the put-in-brig modal, and displays a success toast.
     */
    public function confirmPutInBrig(): void
    {
        if (! Auth::user()->can('put-in-brig')) {
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
     * the `release-from-brig` permission, no action is taken.
     */
    public function openReleaseFromBrigModal(): void
    {
        if (! Auth::user()->can('release-from-brig')) {
            return;
        }
        $this->brigActionReason = '';
        Flux::modal('profile-release-from-brig-modal')->show();
    }

    /**
     * Release the component's user from the Brig after validating a release reason.
     *
     * Requires the current user to have the `release-from-brig` permission. Validates
     * that `brigActionReason` is provided and at least 5 characters long, executes the
     * release action, refreshes the user model, closes the release modal, and shows a
     * success toast announcing the release.
     */
    public function confirmReleaseFromBrig(): void
    {
        if (! Auth::user()->can('release-from-brig')) {
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
        if ($this->user->membership_level->value < MembershipLevel::Stowaway->value) {
            return null;
        }

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

    public function openEditUserModal(): void
    {
        $this->authorize('update', $this->user);

        $this->editUserData = [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'date_of_birth' => $this->user->date_of_birth?->format('Y-m-d') ?? '',
            'parent_email' => $this->user->parent_email ?? '',
        ];
        $this->editingUser = true;
        Flux::modal('profile-edit-user-modal')->show();
    }

    public function saveEditUser(): void
    {
        $this->authorize('update', $this->user);

        $this->validate([
            'editUserData.name' => ['required', 'string', 'max:32'],
            'editUserData.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user->id)],
            'editUserData.date_of_birth' => ['nullable', 'date', 'before:today'],
            'editUserData.parent_email' => ['nullable', 'email'],
        ]);

        $oldParentEmail = $this->user->parent_email;
        $newParentEmail = $this->editUserData['parent_email'] ?: null;

        $this->user->update([
            'name' => $this->editUserData['name'],
            'email' => $this->editUserData['email'],
            'date_of_birth' => $this->editUserData['date_of_birth'] ?: null,
            'parent_email' => $newParentEmail,
        ]);

        if ($newParentEmail && strtolower($newParentEmail ?? '') !== strtolower($oldParentEmail ?? '')) {
            \App\Actions\LinkParentByEmail::run($this->user);
        }

        \App\Actions\RecordActivity::run($this->user, 'update_profile', 'User profile updated.');

        $this->user->refresh();
        $this->editingUser = false;
        Flux::modal('profile-edit-user-modal')->close();
        Flux::toast(text: 'User details updated.', heading: 'Updated', variant: 'success');
    }

}; ?>

<div>
    {{-- Row 1: Core Identity --}}
    <div class="w-full flex flex-col md:flex-row gap-4">
        {{-- User Info Card --}}
        <flux:card class="w-full md:w-1/2 lg:w-1/3 p-6 flex flex-col">
            <div class="space-y-2 flex-1">
                <div class="flex items-center gap-3 flex-wrap mb-2">
                    <flux:heading size="xl">{{ $user->name }}</flux:heading>
                    @if($user->isInBrig())
                        <flux:badge color="red">In the Brig</flux:badge>
                    @endif
                    @if($user->parents->isNotEmpty())
                        <flux:badge color="purple" size="sm">Child Account</flux:badge>
                    @endif
                    @can('viewPii', $user)
                        @if($user->date_of_birth)
                            @php
                                $age = \Carbon\Carbon::parse($user->date_of_birth)->age;
                                $ageColor = $age < 13 ? 'red' : ($age <= 16 ? 'blue' : 'zinc');
                            @endphp
                            <flux:badge color="{{ $ageColor }}" size="sm">Age {{ $age }}</flux:badge>
                        @endif
                    @endcan
                </div>
                <flux:text>Member Rank: {{ $user->membership_level->label() }}</flux:text>
                <flux:text>Joined on {{ $user->created_at->format('F j, Y') }}</flux:text>
                @can('manage-stowaway-users')
                    @if($user->registration_answer)
                        <div class="flex items-center gap-2">
                            <flux:text>Registration Question</flux:text>
                            <flux:button size="xs" variant="ghost" icon="magnifying-glass" x-on:click="$flux.modal('profile-registration-question-modal').show()" />
                        </div>
                    @endif
                @endcan
            </div>

            @canany(['update', 'manage-stowaway-users', 'put-in-brig', 'release-from-brig'], $user)
                <div class="pt-3 mt-auto">
                    <flux:dropdown position="bottom" align="start">
                        <flux:button variant="primary" icon="ellipsis-vertical" size="sm">Actions</flux:button>
                        <flux:menu>
                            @can('update', $user)
                                <flux:menu.item icon="pencil-square" wire:click="openEditUserModal">
                                    Edit User
                                </flux:menu.item>
                            @endcan

                            @can('release-from-brig')
                                @if($user->isInBrig())
                                    <flux:menu.item icon="lock-open" wire:click="openReleaseFromBrigModal">
                                        Release from Brig
                                    </flux:menu.item>
                                @endif
                            @endcan

                            @can('put-in-brig')
                                @if(! $user->isInBrig() && ! $user->staffPosition && $user->id !== Auth::id())
                                    <flux:menu.item icon="lock-closed" wire:click="openPutInBrigModal">
                                        Put in Brig
                                    </flux:menu.item>
                                @endif
                            @endcan

                            @can('manage-stowaway-users')
                                @if(! $user->isInBrig() && ($this->nextMembershipLevel !== null || $this->previousMembershipLevel !== null))
                                    <flux:menu.separator />
                                @endif

                                @if(! $user->isInBrig() && $this->nextMembershipLevel !== null)
                                    <flux:menu.item icon="arrow-up-circle" x-on:click="$flux.modal('profile-promote-confirm-modal').show()">
                                        Promote to {{ $this->nextMembershipLevel->label() }}
                                    </flux:menu.item>
                                @endif

                                @if(! $user->isInBrig() && $this->previousMembershipLevel !== null)
                                    <flux:menu.item icon="arrow-down-circle" x-on:click="$flux.modal('profile-demote-confirm-modal').show()">
                                        Demote to {{ $this->previousMembershipLevel->label() }}
                                    </flux:menu.item>
                                @endif
                            @endcan
                        </flux:menu>
                    </flux:dropdown>
                </div>
            @endcanany
        </flux:card>

        {{-- Linked Accounts Card --}}
        <flux:card class="w-full md:w-1/2 lg:w-1/3 p-6">
            <flux:heading size="xl" class="mb-4">Linked Accounts</flux:heading>

            {{-- Minecraft Section --}}
            <div class="mb-4">
                <div class="flex items-center mb-2">
                    <flux:text class="font-medium text-sm text-zinc-600 dark:text-zinc-400 uppercase tracking-wide">Minecraft</flux:text>
                    @if(auth()->id() === $user->id)
                        <flux:spacer />
                        <flux:button href="{{ route('settings.minecraft-accounts') }}" size="xs" variant="primary">Manage</flux:button>
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
                                                wire:click="confirmForceDelete({{ $account->id }})"
                                                variant="ghost"
                                                size="sm"
                                                class="hover:!text-red-600 dark:hover:!text-red-400 hover:!bg-red-50 dark:hover:!bg-red-950">
                                                Delete
                                            </flux:button>
                                        @endcan
                                    </div>
                                @elseif($account->status !== \App\Enums\MinecraftAccountStatus::Cancelled && $account->status !== \App\Enums\MinecraftAccountStatus::Cancelling && $account->status !== \App\Enums\MinecraftAccountStatus::Removed)
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
                @endif
            </div>

            <flux:separator />

            {{-- Discord Section --}}
            <div class="mt-4">
                <div class="flex items-center mb-2">
                    <flux:text class="font-medium text-sm text-zinc-600 dark:text-zinc-400 uppercase tracking-wide">Discord</flux:text>
                    @if(auth()->id() === $user->id)
                        <flux:spacer />
                        <flux:button href="{{ route('settings.discord-account') }}" size="xs" variant="primary">Manage</flux:button>
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
            </div>
        </flux:card>

        {{-- Family Card --}}
        @if($user->parents->isNotEmpty() || $user->children->isNotEmpty())
            <flux:card class="w-full md:w-1/2 lg:w-1/3 p-6 flex flex-col">
                <div class="flex-1">
                    <flux:heading size="xl" class="mb-4">Family</flux:heading>

                    @if($user->parents->isNotEmpty())
                        <div class="mb-4">
                            <flux:text class="font-medium text-sm text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-2">Parents</flux:text>
                            @foreach($user->parents as $parentUser)
                                <div wire:key="family-parent-{{ $parentUser->id }}" class="mb-1">
                                    <flux:link href="{{ route('profile.show', $parentUser) }}" class="text-sm">{{ $parentUser->name }}</flux:link>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($user->children->isNotEmpty())
                        <div>
                            <flux:text class="font-medium text-sm text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-2">Children</flux:text>
                            @foreach($user->children as $childUser)
                                <div wire:key="family-child-{{ $childUser->id }}" class="mb-1">
                                    <flux:link href="{{ route('profile.show', $childUser) }}" class="text-sm">{{ $childUser->name }}</flux:link>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @can('viewAny', \App\Models\User::class)
                    @if($user->children->isNotEmpty())
                        <div class="pt-3 mt-auto">
                            <flux:link href="{{ route('parent-portal.show', $user) }}" class="text-sm">
                                View Parent Portal
                            </flux:link>
                        </div>
                    @endif
                @endcan
            </flux:card>
        @endif
    </div>

    {{-- Row 2: Staff Management --}}
    @php
        $showContactInfo = Auth::user()->can('viewPii', $user) || (Auth::user()->can('viewStaffPhone', $user) && $user->staff_phone);
        $showStaffMgmt = $user->staffPosition
            ? Auth::user()->can('assign', $user->staffPosition)
            : Auth::user()->can('viewAny', \App\Models\StaffPosition::class);
    @endphp
    @if($showContactInfo || $showStaffMgmt)
        <div class="w-full flex flex-col md:flex-row gap-4 mt-6">
            {{-- Contact Info Card --}}
            @can('viewPii', $user)
                <flux:card class="w-full md:w-1/2 lg:w-1/3 p-6 space-y-2">
                    <flux:heading size="xl" class="mb-4">Contact Information</flux:heading>
                    <flux:text>Email: {{ $user->email }}</flux:text>
                    @can('viewStaffPhone', $user)
                        @if($user->staff_phone)
                            <flux:text>Phone: {{ $user->staff_phone }}</flux:text>
                        @endif
                    @endcan
                </flux:card>
            @endcan

            @cannot('viewPii', $user)
                @can('viewStaffPhone', $user)
                    @if($user->staff_phone)
                        <flux:card class="w-full md:w-1/2 lg:w-1/3 p-6 space-y-2">
                            <flux:heading size="xl" class="mb-4">Contact Information</flux:heading>
                            <flux:text>Phone: {{ $user->staff_phone }}</flux:text>
                        </flux:card>
                    @endif
                @endcan
            @endcannot

            {{-- Staff Management Card --}}
            @if($user->staffPosition)
                @can('assign', $user->staffPosition)
                    <flux:card class="w-full md:w-1/2 lg:w-1/3 p-6">
                        <flux:heading size="xl">{{ $user->staffPosition->title }}</flux:heading>
                        <flux:text>Department: {{ $user->staffPosition->department->label() }}</flux:text>
                        <flux:text>Rank: {{ $user->staff_rank->label() }}</flux:text>
                        @if($user->staffPosition->description)
                            <flux:text variant="subtle" class="mt-2">{{ $user->staffPosition->description }}</flux:text>
                        @endif

                        <div class="mt-4 flex gap-2">
                            <flux:button wire:click="removeFromPosition" variant="ghost" class="hover:!text-red-600 dark:hover:!text-red-400 hover:!bg-red-50 dark:hover:!bg-red-950">Remove from Staff</flux:button>
                            <flux:spacer />
                            <flux:modal.trigger name="assign-staff-position">
                                <flux:button size="sm">Change Position</flux:button>
                            </flux:modal.trigger>
                        </div>
                    </flux:card>
                @endcan
            @else
                @can('viewAny', \App\Models\StaffPosition::class)
                    <flux:card class="w-full md:w-1/2 lg:w-1/3 p-6">
                        <flux:heading size="xl">Staff Config</flux:heading>
                        <div class="mt-6 text-center">
                            <flux:modal.trigger name="assign-staff-position">
                                <flux:button>Assign Staff Position</flux:button>
                            </flux:modal.trigger>
                        </div>
                    </flux:card>
                @endcan
            @endif
        </div>
    @endif

    {{-- Row 3: Details & Reports --}}
    @php
        $hasStaffDetails = $user->staffPosition !== null;
    @endphp
    @if($hasStaffDetails || Auth::user()->can('view-user-discipline-reports', $user))
        <div class="w-full flex flex-col md:flex-row gap-4 mt-6">
            {{-- Staff Details Card --}}
            @if($hasStaffDetails)
                <flux:card class="w-full lg:w-1/2 p-6 space-y-4">
                    <flux:heading size="lg">Staff Details</flux:heading>

                    <div class="flex items-start gap-4">
                        @if(! $user->isJrCrew() && $user->staffPhotoUrl())
                            <img src="{{ $user->staffPhotoUrl() }}" alt="{{ $user->name }}" class="w-24 h-24 rounded-lg object-cover flex-shrink-0" />
                        @elseif($user->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="w-16 h-16 rounded-lg flex-shrink-0" />
                        @endif

                        <div class="space-y-1">
                            @if(! $user->isJrCrew() && $user->staff_first_name)
                                <flux:heading size="md">{{ $user->staff_first_name }} {{ $user->staff_last_initial }}.</flux:heading>
                            @endif
                            <flux:link href="{{ route('profile.show', $user) }}" wire:navigate class="text-sm text-zinc-500">{{ $user->name }}</flux:link>

                            <div class="flex gap-2 mt-1">
                                <flux:badge size="sm" color="{{ $user->staff_rank->color() }}">{{ $user->staff_rank->label() }}</flux:badge>
                                <flux:badge size="sm" color="zinc">{{ $user->staffPosition->department->label() }}</flux:badge>
                            </div>
                        </div>
                    </div>

                    <div>
                        <flux:heading size="sm">{{ $user->staffPosition->title }}</flux:heading>
                        @if($user->staffPosition->description)
                            <flux:text variant="subtle" class="mt-1">{{ $user->staffPosition->description }}</flux:text>
                        @endif
                    </div>

                    @if(Auth::user()->hasRole('Staff Access'))
                        @php
                            $position = $user->staffPosition->load('roles');
                            $rankRoles = $user->staff_rank && $user->staff_rank !== \App\Enums\StaffRank::None
                                ? \App\Models\Role::whereIn('id',
                                    \Illuminate\Support\Facades\DB::table('role_staff_rank')
                                        ->where('staff_rank', $user->staff_rank->value)
                                        ->pluck('role_id')
                                )->orderBy('name')->get()
                                : collect();
                        @endphp

                        {{-- Position Roles --}}
                        @if($position->has_all_roles_at)
                            <div>
                                <flux:heading size="sm" class="mb-1">Position Roles</flux:heading>
                                <flux:badge size="sm" color="amber" icon="star">Allow All</flux:badge>
                            </div>
                        @elseif($position->roles->isNotEmpty())
                            <div>
                                <flux:heading size="sm" class="mb-1">Position Roles</flux:heading>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($position->roles as $role)
                                        <flux:badge size="sm" color="{{ $role->color }}" icon="{{ $role->icon }}">{{ $role->name }}</flux:badge>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Rank Roles --}}
                        @if($rankRoles->isNotEmpty())
                            <div>
                                <flux:heading size="sm" class="mb-1">{{ $user->staff_rank->label() }} Roles</flux:heading>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($rankRoles as $role)
                                        <flux:badge size="sm" color="{{ $role->color }}" icon="{{ $role->icon }}">{{ $role->name }}</flux:badge>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif

                    @if(! $user->isJrCrew() && $user->staff_bio)
                        <div>
                            <flux:heading size="sm" class="mb-1">About</flux:heading>
                            <flux:text>{!! nl2br(e($user->staff_bio)) !!}</flux:text>
                        </div>
                    @endif

                    @if(Auth::id() === $user->id && $user->isAtLeastRank(StaffRank::CrewMember))
                        <div>
                            <flux:link href="{{ route('settings.staff-bio') }}" target="_blank" icon="pencil-square">
                                Update Staff Bio
                            </flux:link>
                        </div>
                    @endif
                </flux:card>
            @endif

            {{-- Staff Reports Card --}}
            @can('view-user-discipline-reports', $user)
                <div class="w-full lg:w-1/2">
                    <livewire:users.discipline-reports-card :user="$user" lazy />
                </div>
            @endcan
        </div>
    @endif

    {{-- Modals --}}
    <x-minecraft.mc-account-detail-modal :account="$selectedAccount" />

    @can('viewAny', \App\Models\StaffPosition::class)
        <flux:modal name="assign-staff-position" class="w-full md:w-1/2 xl:w-1/3">
            <div class="space-y-6">
                <flux:heading size="lg">Assign Staff Position</flux:heading>
                <flux:text variant="subtle">Select a vacant position to assign to {{ $user->name }}.</flux:text>

                @if($this->availablePositions->isEmpty())
                    <flux:text>No vacant positions available. Create one in the ACP first.</flux:text>
                @else
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        @foreach($this->availablePositions as $pos)
                            <div wire:key="assign-pos-{{ $pos->id }}" class="flex items-center justify-between p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <div>
                                    <span class="font-medium">{{ $pos->title }}</span>
                                    <div class="flex gap-1 mt-1">
                                        <flux:badge size="sm" color="{{ $pos->rank->color() }}">{{ $pos->rank->label() }}</flux:badge>
                                        <flux:badge size="sm" color="zinc">{{ $pos->department->label() }}</flux:badge>
                                    </div>
                                </div>
                                <flux:button wire:click="assignToPosition({{ $pos->id }})" size="sm" variant="primary">Assign</flux:button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <flux:button variant="ghost" x-on:click="$flux.modal('assign-staff-position').close()">Cancel</flux:button>
            </div>
        </flux:modal>
    @endcan

    <!-- Edit User Modal -->
    <flux:modal name="profile-edit-user-modal" variant="flyout">
        <div class="space-y-6">
            <flux:heading size="xl">Edit User</flux:heading>

            <form wire:submit="saveEditUser" class="space-y-6">
                <flux:field>
                    <flux:label>Username</flux:label>
                    <flux:input wire:model="editUserData.name" required maxlength="32" />
                    <flux:error name="editUserData.name" />
                </flux:field>

                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input wire:model="editUserData.email" type="email" required />
                    <flux:error name="editUserData.email" />
                </flux:field>

                <flux:field>
                    <flux:label>Date of Birth</flux:label>
                    <flux:input wire:model="editUserData.date_of_birth" type="date" />
                    <flux:error name="editUserData.date_of_birth" />
                </flux:field>

                <flux:field>
                    <flux:label>Parent Email</flux:label>
                    <flux:input wire:model="editUserData.parent_email" type="email" placeholder="Optional" />
                    <flux:error name="editUserData.parent_email" />
                </flux:field>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">Save</flux:button>
                </div>
            </form>
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

    {{-- Force Delete Minecraft account confirmation modal --}}
    <flux:modal name="confirm-force-delete-mc-account" class="min-w-[22rem] space-y-6">
        <div>
            <flux:heading size="lg">Permanently Delete Minecraft Account</flux:heading>
            <flux:text class="mt-2">This will <strong>permanently</strong> delete this Minecraft account and release the UUID so it can be registered by anyone. This action cannot be undone.</flux:text>
        </div>

        <div class="flex gap-2 justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="forceDeleteMinecraftAccount">Delete Permanently</flux:button>
        </div>
    </flux:modal>

    {{-- Registration Question Modal --}}
    @can('manage-stowaway-users')
        @if($user->registration_answer)
            <flux:modal name="profile-registration-question-modal" class="w-full md:w-1/2 xl:w-1/3">
                <div class="space-y-4">
                    <flux:heading size="lg">Registration Question</flux:heading>

                    <div>
                        <flux:text variant="subtle" class="font-medium text-sm">Question Asked</flux:text>
                        <flux:text class="italic">{{ $user->registration_question_text ?? 'N/A' }}</flux:text>
                    </div>

                    <div>
                        <flux:text variant="subtle" class="font-medium text-sm">Response</flux:text>
                        <flux:text>{{ $user->registration_answer }}</flux:text>
                    </div>

                    <div class="flex justify-end">
                        <flux:button variant="ghost" x-on:click="$flux.modal('profile-registration-question-modal').close()">Close</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    @endcan

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
