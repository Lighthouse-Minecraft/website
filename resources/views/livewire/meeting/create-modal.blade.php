<?php

use Livewire\Volt\Component;
use App\Models\Meeting;
use Flux\Flux;

new class extends Component {
    public $title;
    public $day;
    public $time;
}; ?>

<div>
    <flux:modal.trigger name='meeting-create-modal'>
        <flux:button variant="primary">
            Create a Meeting
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name='meeting-create-modal'>
        <div class="text-left space-y-6">
            <flux:heading size="xl">Create a New Meeting</flux:heading>

            <flux:text>
                Fill out the form below to create a new meeting. Ensure all required fields are completed.
            </flux:text>

            <flux:input wire:model="title" name="title" label="Meeting Title" required />
            <flux:date-picker wire:model="day" name="day" label="Meeting Date" required />
            <flux:input type="time" wire:model="time" name="time" label="Meeting Time (Eastern Time - ET)" required />
            <flux:link href="https://time.is/ET" target="_blank" color="secondary">
                Need help converting time zones?
            </flux:link>

            <div class="text-right w-full mt-6">
                <flux:button data-testid="meeting-create.store" variant="primary" wire:click="createMeeting">
                    Create Meeting
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
