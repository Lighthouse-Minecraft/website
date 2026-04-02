<?php

use App\Enums\MembershipLevel;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {

    public function acceptRules()
    {
        $result = \App\Actions\AgreeToRules::run(auth()->user(), auth()->user());

        if (! $result['success']) {
            Flux::toast($result['message'], 'Error', variant: 'danger');

            return;
        }

        Flux::modal('view-rules-modal')->close();
        Flux::toast('Rules accepted successfully! Promoted to Stowaway.', 'Success', variant: 'success');

        return redirect()->route('dashboard');
    }
}; ?>

<div>
    <flux:modal.trigger name="view-rules-modal">
        @if (auth()->user()->rules_accepted_at)
            <flux:button size="xs">View Rules</flux:button>
        @else
            <flux:button variant="primary">Read &amp; Accept Rules</flux:button>
        @endif
    </flux:modal.trigger>

    <flux:modal name="view-rules-modal" size="lg" variant="flyout" class="w-full md:w-2/3">
        @include('partials.community-rules')
        <div class="w-full text-right">
            @if (!auth()->user()->rules_accepted_at || auth()->user()->isLevel(MembershipLevel::Drifter))
                <flux:button color="amber" wire:click="acceptRules" variant="primary">I Have Read the Rules and Agree to Follow Them</flux:button>
            @endif
        </div>
    </flux:modal>
</div>
