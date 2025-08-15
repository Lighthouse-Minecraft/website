<?php

use Livewire\Volt\Component;

new class extends Component {
    public $meetings;

    public function mount()
    {
        // $this->meetings = Meeting::where('status', )
    }

}; ?>

<div>
    <h2 class="text-lg font-medium">Upcoming Meetings</h2>

    <ul class="mt-2">
        @foreach ($meetings as $meeting)
            <li>
                <a href="{{ route('meeting.edit', $meeting) }}">{{ $meeting->title }}</a>
            </li>
        @endforeach
    </ul>
</div>
