<?php

use App\Enums\MembershipLevel;
use App\Enums\StaffRank;
use App\Services\DocumentationService;
use Livewire\Volt\Component;

new class extends Component {
    public function getVisibleGuidesProperty()
    {
        $service = app(DocumentationService::class);
        $user = auth()->user();

        return $service->getAllGuides()->filter(function ($guide) use ($user) {
            return $this->canSee($guide->visibility, $user);
        });
    }

    private function canSee(string $visibility, $user): bool
    {
        if ($visibility === 'public') {
            return true;
        }
        if (! $user) {
            return false;
        }

        return match ($visibility) {
            'users' => ! $user->in_brig,
            'resident' => ! $user->in_brig && $user->isAtLeastLevel(MembershipLevel::Resident),
            'citizen' => ! $user->in_brig && $user->isAtLeastLevel(MembershipLevel::Citizen),
            'staff' => $user->isAtLeastRank(StaffRank::JrCrew) || $user->hasRole('Admin'),
            'officer' => $user->isAtLeastRank(StaffRank::Officer) || $user->hasRole('Admin'),
            default => false,
        };
    }
}; ?>

<section>
    <div class="mx-auto max-w-4xl p-6">
        <flux:card>
            <flux:heading size="xl">Guides</flux:heading>
            <flux:text variant="subtle">Quick-start guides and tutorials</flux:text>
            <flux:separator class="my-4" />

            @if($this->visibleGuides->isEmpty())
                <flux:text variant="subtle">No guides available.</flux:text>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach($this->visibleGuides as $guide)
                        <flux:card wire:key="guide-{{ $guide->slug }}">
                            <flux:heading size="md">
                                <flux:link href="{{ $guide->url }}" wire:navigate>{{ $guide->title }}</flux:link>
                            </flux:heading>
                            @if($guide->summary)
                                <flux:text variant="subtle" class="mt-1">{{ $guide->summary }}</flux:text>
                            @endif
                        </flux:card>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>
</section>
