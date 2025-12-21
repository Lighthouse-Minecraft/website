<?php

use Livewire\Volt\Component;
use App\Models\Meeting;

new class extends Component {
    public $meetings;

    public function mount() {
        $this->meetings = Meeting::where('status', 'pending')
            ->orderBy('scheduled_time', 'asc')
            ->take(3)
            ->get();
    }
}; ?>

<div class="space-y-6">
    <flux:heading>Upcoming Meetings</flux:heading>
    <ul>
        @foreach($meetings as $meeting)
            <li class="my-4">
                <flux:link href="{{ route('meeting.edit', $meeting) }}">
                    {{ $meeting->title }}
                    <flux:text variant="subtle">{{ $meeting->scheduled_time->format('m/d/Y \@ g:i a') }} ET</flux:text>
                </flux:link>
            </li>
        @endforeach
    </ul>
</div>
