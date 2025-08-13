<?php

use App\Models\Meeting;
use Livewire\Volt\Component;

new class extends Component {
    public Meeting $meeting;

    public function mount(Meeting $meeting) {
        $this->meeting = $meeting;
    }

    public function StartMeeting() {
        $this->meeting->startMeeting();

        // Look up the agenda
        $agendaNote = $this->meeting->notes()->where('section_key', 'agenda')->first();
        if ($agendaNote) {
            // Copy the agenda content to the meeting record
            $this->meeting->agenda = $agendaNote->content;
            $this->meeting->save();
        }
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{  $meeting->title }} - {{  $meeting->day }}</flux:heading>


    <div class="text-right w-full">
        <flux:button wire:click="StartMeeting" variant="primary">Start Meeting</flux:button>
    </div>

    @if ($meeting->status == \App\Enums\MeetingStatus::Pending)
        <flux:heading variant="primary">Agenda</flux:heading>
        <livewire:note.editor :meeting="$meeting" section_key="agenda"/>
    @else
        <div class="w-3/4 mx-auto">
            <flux:card>
                <flux:heading class="mb-4">Meeting Agenda</flux:heading>

                <flux:text>{!! nl2br($meeting->agenda) !!}</flux:text>
            </flux:card>
        </div>
    @endif

    @if ($meeting->status != \App\Enums\MeetingStatus::Pending)
        <livewire:meeting.department-section :meeting="$meeting" departmentValue="general" :key="'department-section-general'" />
        <flux:separator />
        @foreach(\App\Enums\StaffDepartment::cases() as $department)
            <livewire:meeting.department-section :meeting="$meeting" :departmentValue="$department->value" :key="'department-section-' . $department->value" />
            <flux:separator />
        @endforeach
    @endif
</div>
