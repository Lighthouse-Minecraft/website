<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Enums\MembershipLevel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Flux\Flux;

new class extends Component {
    public User $user;
    public $currentDepartment;
    public $currentDepartmentValue;
    public $currentTitle;
    public $currentRank;
    public $departments;
    public $ranks;

    public function mount(User $user) {
        $this->user = $user;
        $this->currentDepartment = $user->staff_department?->name ?? 'None';
        $this->currentDepartmentValue = $user->staff_department?->value ?? null;
        $this->currentTitle = $user->staff_title;
        $this->currentRank = $user->staff_rank?->value;
        $this->departments = \App\Enums\StaffDepartment::cases();
        $this->ranks = \App\Enums\StaffRank::cases();
    }

    public function updateStaffPosition() {
        if (!Auth::user()->isAdmin() && !Auth::user()->isInDepartment(\App\Enums\StaffDepartment::Command) && !Auth::user()->isAtLeastRank(\App\Enums\StaffRank::Officer)) {
            $this->dispatch('notify', 'You do not have permission to update staff positions.');
            return;
        }

        $department = $this->currentDepartmentValue
            ? \App\Enums\StaffDepartment::tryFrom($this->currentDepartmentValue)
            : null;

        $rank = $this->currentRank !== null
            ? \App\Enums\StaffRank::tryFrom((int) $this->currentRank)
            : null;

        $this->validate([
            'currentTitle' => [Rule::requiredIf($this->currentDepartmentValue !== null), 'string', 'max:255'],
            'currentDepartmentValue' => ['nullable', Rule::enum((\App\Enums\StaffDepartment::class))],
            'currentRank' => ['nullable', Rule::enum((\App\Enums\StaffRank::class))],
        ]);

        $success = \App\Actions\SetUsersStaffPosition::run(
            $this->user,
            $this->currentTitle,
            $department,
            $rank
        );

        if ($success) {
            Flux::toast('Staff position updated successfully.', 'Success', 'success');
        } else {
            Flux::toast('Failed to update staff position. Please try again.', 'Error', 'error');
        }

        Flux::modal('manage-users-staff-position')->close();
    }

    public function removeStaffPosition() {
        if (!Auth::user()->isAdmin() && !Auth::user()->isInDepartment(\App\Enums\StaffDepartment::Command) && !Auth::user()->isAtLeastRank(\App\Enums\StaffRank::Officer)) {
            $this->dispatch('notify', 'You do not have permission to remove staff positions.');
            return;
        }

        $success = \App\Actions\RemoveUsersStaffPosition::run($this->user);

        if ($success) {
            Flux::toast('Staff position removed successfully.', 'Success', 'success');
        } else {
            Flux::toast('Failed to remove staff position. Please try again.', 'Error', 'error');
        }

        Flux::modal('manage-users-staff-position')->close();
    }

}; ?>

<div class="w-full block md:flex md:mr-4">
    <flux:card class="w-full md:w-1/2 lg:w-1/3 p-6 space-y-2">
        <flux:heading size="xl" class="mb-4">{{ $user->name }}</flux:heading>
        <flux:text>Member Rank: {{ $user->membership_level->label() }}</flux:text>
        <flux:text>Joined on {{ $user->created_at->format('F j, Y') }}</flux:text>
    </flux:card>

    @if($user->staff_department)
        <flux:card class="w-full md:w-1/2 lg:w-1/3 md:mx-4">
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
        <flux:card class="w-full md:w-1/4 md:mx-4">
            <flux:heading size="xl">Staff Config</flux:heading>
            <div class="mt-6 text-center">
                <flux:modal.trigger name="manage-users-staff-position">
                    <flux:button>Manage Staff Position</flux:button>
                </flux:modal.trigger>
            </div>
        </flux:card>
    @endif

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
                <flux:button wire:click="removeStaffPosition" variant="danger" class="opacity-80">Remove Staff Position</flux:button>
                <flux:spacer />
                <flux:button wire:click="updateStaffPosition" variant="primary">Save Changes</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
