<?php

use App\Enums\MeetingStatus;
use App\Enums\StaffDepartment;
use App\Models\Meeting;
use Livewire\Volt\Component;

new class extends Component {
    public Meeting $meeting;
    public $pollTime = 0;

    public function mount(Meeting $meeting) {
        $this->meeting = $meeting;

        if ($meeting->status == MeetingStatus::Pending && $meeting->day == now()->format('Y-m-d'))
        {
            $this->pollTime = 15;
        }
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

<div class="space-y-6" wire:poll.{{  $pollTime }}>
    <flux:heading size="xl">{{  $meeting->title }} - {{  $meeting->day }}</flux:heading>


    <div class="text-right w-full">
        @if ($this->meeting->status == MeetingStatus::Pending)
            <flux:button wire:click="StartMeeting" variant="primary">Start Meeting</flux:button>
        @endif
    </div>

    @if ($meeting->status == MeetingStatus::Pending)
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

    @if ($meeting->status != MeetingStatus::Pending)
        <livewire:meeting.department-section :meeting="$meeting" departmentValue="general" :key="'department-section-general'" />
        <flux:separator />

        @foreach(StaffDepartment::cases() as $department)
            <livewire:meeting.department-section :meeting="$meeting" :departmentValue="$department->value" :key="'department-section-' . $department->value" />
            <flux:separator />
        @endforeach
    @endif
</div>
