<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Enums\MembershipLevel;

new class extends Component {
    public User $user;
}; ?>

<div>
    <flux:card class="w-full md:w-1/2 lg:w-1/3 p-6 space-y-2">
        <flux:heading size="xl" class="mb-4">{{ $user->name }}</flux:heading>
        <flux:text>Member Rank: {{ $user->membership_level->label() }}</flux:text>
        <flux:text>Joined on {{ $user->created_at->format('F j, Y') }}</flux:text>
    </flux:card>
</div>
