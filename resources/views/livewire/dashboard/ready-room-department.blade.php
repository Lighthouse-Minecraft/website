<?php

use App\Models\Meeting;
use Livewire\Volt\Component;

new class extends Component {
    public $department;
    public Meeting $meeting;

    public function mount($department)
    {
        $this->department = $department;
        $this->meeting = new Meeting();
    }
}; ?>

<div>
    {{-- <flux:heading size="lg" class="mb-4">Department: {{ ucfirst($department) }}</flux:heading> --}}

    <div class="w-full flex">
        <div class="w-full md:w-1/3 lg:w-1/4">
            <livewire:dashboard.ready-room-upcoming-meetings />
        </div>
        <div class="w-full md:w-2/3 lg:w-3/4">
            <livewire:task.department-list :section_key="$department" :meeting="$meeting" />
        </div>
    </div>

    <div class="mt-6">
        <livewire:meeting.notes-display :section-key="$department" />
    </div>
</div>
