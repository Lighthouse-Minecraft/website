<?php

use App\Models\Meeting;
use Livewire\Volt\Component;

new class extends Component {
    public Meeting $meeting;
    public string $section_key;

    public function mount(Meeting $meeting, string $section_key) {
        $this->meeting = $meeting;
        $this->section_key = $section_key;
    }
}; ?>

<div>
    <flux:heading class="mb-4">{{ ucfirst($section_key) }} Tasks</flux:heading>

    <flux:input.group>
        <flux:input placeholder="Task Name" />

        <flux:button icon="plus">Add Task</flux:button>
    </flux:input.group>
</div>
