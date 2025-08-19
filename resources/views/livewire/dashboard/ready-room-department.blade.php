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

    <livewire:task.department-list :section_key="$department" :meeting="$meeting" />
</div>
