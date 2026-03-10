<?php

use App\Enums\MembershipLevel;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component {
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user;
    }
}; ?>

<div>
    @if(
        auth()->user()?->can('manage-stowaway-users')
        && $user->membership_level === MembershipLevel::Stowaway
        && $user->registration_answer
    )
        <flux:card class="w-full">
            <flux:heading size="md" class="mb-2">Registration Response</flux:heading>

            <div class="space-y-3">
                <div>
                    <flux:text variant="subtle" size="sm" class="font-medium">Question Asked</flux:text>
                    <flux:text size="sm" class="italic">{{ $user->registration_question_text ?? 'N/A' }}</flux:text>
                </div>
                <div>
                    <flux:text variant="subtle" size="sm" class="font-medium">Answer</flux:text>
                    <flux:text>{{ $user->registration_answer }}</flux:text>
                </div>
            </div>
        </flux:card>
    @endif
</div>
