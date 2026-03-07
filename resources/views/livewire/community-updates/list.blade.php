<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Meeting;
use App\Enums\MeetingStatus;
use Illuminate\Support\Facades\Gate;

new class extends Component {
    use WithPagination;

    public function getCanViewAllProperty(): bool
    {
        return Gate::check('view-all-community-updates');
    }

    public function with(): array
    {
        $query = Meeting::query()
            ->where('status', MeetingStatus::Completed->value)
            ->where('show_community_updates', true)
            ->orderBy('day', 'desc');

        if ($this->canViewAll) {
            $meetings = $query->paginate(10);
        } else {
            $meetings = $query->limit(1)->get();
        }

        return [
            'meetings' => $meetings,
        ];
    }
}; ?>

<div class="space-y-6">
    <flux:accordion exclusive>
        @forelse($meetings as $meeting)
            <flux:accordion.item
                :heading="$meeting->title . ' - ' . $meeting->day"
                :expanded="$loop->first"
                transition
            >
                <flux:card class="text-left">
                    <div class="prose max-w-none">
                        {!! nl2br($meeting->community_minutes) !!}
                    </div>
                </flux:card>
            </flux:accordion.item>
        @empty
            <p class="text-zinc-500 text-center py-8">No community updates available.</p>
        @endforelse
    </flux:accordion>

    @if($this->canViewAll)
        @if($meetings->hasPages())
            <div class="mt-6">
                {{ $meetings->links() }}
            </div>
        @endif
    @else
        <flux:card class="text-center">
            <flux:text variant="subtle">Community members can see all past updates. Join our community to access the full archive!</flux:text>
        </flux:card>
    @endif
</div>
