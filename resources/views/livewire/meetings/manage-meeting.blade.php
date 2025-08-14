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

    public function EndMeeting() {
        $this->authorize('update', $this->meeting);

        $this->modal('end-meeting-confirmation')->show();
    }

    public function EndMeetingConfirmed() {
        $this->authorize('update', $this->meeting);

        $this->meeting->endMeeting();

        $this->modal('end-meeting-confirmation')->close();
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

    <div class="w-full text-right">
        @can('update', $meeting)

            <flux:button wire:click="EndMeeting" variant="primary">End Meeting</flux:button>
        @endcan
    </div>

    {{-- End Meeting Confirmation Modal --}}
    <flux:modal name="end-meeting-confirmation" class="min-w-[28rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">End Meeting?</flux:heading>

                <flux:text class="mt-2">
                    You're about to end this meeting and move it to the finalizing stage.
                </flux:text>
                <flux:callout color="rose" class="mt-2">
                    <flux:callout.heading>Note:</flux:callout.heading>
                    <flux:callout.text>
                        Once ended, the note fields will no longer be editable and the meeting will be locked for finalization.
                    </flux:callout.text>
                </flux:callout>
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>

                <flux:button wire:click="EndMeetingConfirmed" variant="danger">End Meeting</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
