<?php

use App\Enums\MembershipLevel;
use App\Enums\StaffRank;
use App\Services\DocumentationService;
use Livewire\Volt\Component;

new class extends Component {
    public function getVisibleBooksProperty()
    {
        $service = app(DocumentationService::class);
        $user = auth()->user();

        return $service->getAllBooks()->filter(function ($book) use ($user) {
            return $this->canSee($book->visibility, $user);
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
            <flux:heading size="xl">Handbooks</flux:heading>
            <flux:text variant="subtle">Browse our documentation</flux:text>
            <flux:separator class="my-4" />

            @if($this->visibleBooks->isEmpty())
                <flux:text variant="subtle">No handbooks available.</flux:text>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach($this->visibleBooks as $book)
                        <flux:card wire:key="book-{{ $book->slug }}">
                            <flux:heading size="md">
                                <flux:link href="{{ $book->url }}" wire:navigate>{{ $book->title }}</flux:link>
                            </flux:heading>
                            @if($book->summary)
                                <flux:text variant="subtle" class="mt-1">{{ $book->summary }}</flux:text>
                            @endif
                        </flux:card>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>
</section>
