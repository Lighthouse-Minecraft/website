<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Meeting;
use App\Enums\MeetingStatus;

new class extends Component {
    use WithPagination;

    public function with(): array
    {
        return [
            'meetings' => Meeting::query()
                ->where('status', MeetingStatus::Completed->value)
                ->orderBy('day', 'desc')
                ->paginate(10),
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

    @if($meetings->hasPages())
        <div class="mt-6">
            {{ $meetings->links() }}
        </div>
    @endif
</div>
