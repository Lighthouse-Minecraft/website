<?php

use Livewire\Volt\Component;
use App\Models\Meeting;

new class extends Component {
    public $meetings;

    public function mount()
    {
        $this->meetings = Meeting::all();
    }
}; ?>

<div>
    <ul>
        @foreach ($meetings as $meeting)
            <li>{{ $meeting->day }}</li>
        @endforeach
    </ul>
</div>
