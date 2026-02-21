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

    public function mount(User $user) {
        $this->user = $user;
        $this->user->load('minecraftAccounts');
        $this->currentDepartment = $user->staff_department?->name ?? 'None';
        $this->currentDepartmentValue = $user->staff_department?->value ?? null;
        $this->currentTitle = $user->staff_title;
        $this->currentRank = $user->staff_rank?->value;
        $this->departments = \App\Enums\StaffDepartment::cases();
        $this->ranks = \App\Enums\StaffRank::cases();
    }

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

    public function showAccount(int $accountId): void
    {
        $this->selectedAccount = $this->user->minecraftAccounts()->with('user')->find($accountId);

        if ($this->selectedAccount) {
            $this->modal('mc-account-detail')->show();
        }
    }

    public function revokeMinecraftAccount(int $accountId) {
        if (!Auth::user()->isAdmin()) {
            Flux::toast(
                text: 'You do not have permission to revoke Minecraft accounts.',
                heading: 'Error',
                variant: 'danger'
            );
            return;
        }

        $account = $this->user->minecraftAccounts()->findOrFail($accountId);
        $result = \App\Actions\RevokeMinecraftAccount::run($account, Auth::user());

        if ($result['success']) {
            Flux::toast(
                text: $result['message'],
                heading: 'Success',
                variant: 'success'
            );
            $this->user->refresh();
        } else {
            Flux::toast(
                text: $result['message'],
                heading: 'Error',
                variant: 'danger'
            );
        }
    }

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

    public function openPutInBrigModal(): void
    {
        if (! Auth::user()->can('manage-stowaway-users')) {
            return;
        }
        $this->brigActionReason = '';
        $this->brigActionDays = null;
        Flux::modal('profile-put-in-brig-modal')->show();
    }

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

    public function openReleaseFromBrigModal(): void
    {
        if (! Auth::user()->can('manage-stowaway-users')) {
            return;
        }
        $this->brigActionReason = '';
        Flux::modal('profile-release-from-brig-modal')->show();
    }

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

    public function getNextMembershipLevelProperty(): ?MembershipLevel
    {
        $levels = MembershipLevel::cases();
        $currentIndex = array_search($this->user->membership_level, $levels, strict: true);
        return $levels[$currentIndex + 1] ?? null;
    }

}; ?>

<div>
    <div class="w-full block md:flex md:mr-4">
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
                            class="text-zinc-500 hover:text-red-400"
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
                </div>
            @endcan
        </flux:card>

        @if($user->staff_department)
            <flux:card class="w-full md:w-1/2 lg:w-1/3 md:mx-4 mb-6 md:mb-0">
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
            <flux:card class="w-full md:w-1/4 md:mx-4 mb-6 md:mb-0">
                <flux:heading size="xl">Staff Config</flux:heading>
                <div class="mt-6 text-center">
                    <flux:modal.trigger name="manage-users-staff-position">
                        <flux:button>Manage Staff Position</flux:button>
                    </flux:modal.trigger>
                </div>
            </flux:card>
        @endif

        @can('viewPii', $user)
            <flux:card class="w-full md:w-1/2 lg:w-1/3 {{ $user->staff_department ? 'md:ml-4' : 'md:mx-4' }} p-6 space-y-2">
                <flux:heading size="xl" class="mb-4">Contact Information</flux:heading>
                <flux:text>Email: {{ $user->email }}</flux:text>
            </flux:card>
        @endcan
    </div>

    <div class="w-full md:w-1/3 mt-6">
        <flux:card class="p-6">
            <div class="flex items-center mb-4">
                <flux:heading size="xl">Minecraft Accounts</flux:heading>
                @if(auth()->id() === $user->id)
                    <flux:spacer />
                    <flux:link href="{{ route('settings.minecraft-accounts') }}" class="text-sm text-zinc-400 hover:text-zinc-200">Manage</flux:link>
                @endif
            </div>

            @if($user->minecraftAccounts->isNotEmpty())
                <div class="flex flex-col gap-2">
                    @foreach($user->minecraftAccounts as $account)
                        <div wire:key="minecraft-account-{{ $account->id }}" class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                @if($account->avatar_url)
                                    <img src="{{ $account->avatar_url }}" alt="{{ $account->username }}" class="w-8 h-8 rounded" />
                                @endif
                                <div>
                                    <button wire:click="showAccount({{ $account->id }})" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">{{ $account->username }}</button>
                                    <flux:text class="text-sm text-zinc-500">{{ $account->account_type->label() }}</flux:text>
                                </div>
                            </div>
                            @if(Auth::user()->isAdmin())
                                <flux:button
                                    wire:click="revokeMinecraftAccount({{ $account->id }})"
                                    variant="danger"
                                    size="sm"
                                    wire:confirm="Are you sure you want to revoke this Minecraft account from {{ $user->name }}?">
                                    Revoke
                                </flux:button>
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
                    <flux:button wire:click="removeStaffPosition" variant="danger" class="opacity-80">Remove Staff Position</flux:button>
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
                <flux:textarea wire:model="brigActionReason" rows="4" placeholder="Enter reason..." />
                <flux:error name="brigActionReason" />
            </flux:field>

            <flux:field>
                <flux:label>Days Until Auto-Release</flux:label>
                <flux:description>Optional. Leave blank for no auto-release timer.</flux:description>
                <flux:input wire:model="brigActionDays" type="number" min="1" max="365" placeholder="e.g. 7 (leave blank for no timer)" />
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

    <!-- Release from Brig Modal -->
    <flux:modal name="profile-release-from-brig-modal" class="w-full md:w-1/2 xl:w-1/3">
        <div class="space-y-6">
            <flux:heading size="lg">Release {{ $user->name }} from the Brig</flux:heading>
            <flux:text variant="subtle">This will restore their community access and re-whitelist their Minecraft accounts.</flux:text>

            <flux:field>
                <flux:label>Reason for Release <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="brigActionReason" rows="4" placeholder="Enter reason for release..." />
                <flux:error name="brigActionReason" />
            </flux:field>

            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" x-on:click="$flux.modal('profile-release-from-brig-modal').close()">Cancel</flux:button>
                <flux:button wire:click="confirmReleaseFromBrig" variant="primary">Confirm — Release from Brig</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
