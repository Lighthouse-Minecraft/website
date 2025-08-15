<?php

use App\Models\Meeting;
use Livewire\Volt\Component;

new class extends Component {
    public $meeting;

    public function mount()
    {
        $this->meeting = Meeting::where('status', 'in_progress')->first();
    }
}; ?>

<div>
    @if($meeting)
        @can('attend', $meeting)
            <flux:callout icon="clock" color="sky" inline class="mb-4">
                <flux:callout.heading>{{ $meeting->title }} In Progress!</flux:callout.heading>
                <x-slot name="actions">
                    <flux:button href="{{ route('meeting.edit', $meeting) }}" variant="primary">
                        Join Meeting
                    </flux:button>
                </x-slot>
            </flux:callout>
        @endcan
    @endif
</div>
