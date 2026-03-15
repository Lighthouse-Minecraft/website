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

<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
            <livewire:dashboard.ready-room-upcoming-meetings />
        </div>
        <div class="max-h-[32rem] overflow-y-auto">
            <livewire:task.department-list :section_key="$department" :meeting="$meeting" />
        </div>
        <div>
            <livewire:dashboard.ready-room-department-tickets :department="$department" />
        </div>
    </div>

    <livewire:meeting.notes-display :section-key="$department" />
</div>
