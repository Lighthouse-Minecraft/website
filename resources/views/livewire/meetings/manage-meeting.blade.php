<?php

use App\Enums\MeetingStatus;
use App\Enums\StaffDepartment;
use App\Models\Meeting;
use App\Models\MeetingNote;
use Livewire\Volt\Component;

new class extends Component {
    public Meeting $meeting;
    public $pollTime = 0;

    public function mount(Meeting $meeting) {
        $this->meeting = $meeting->load('attendees');

        // Only poll when meeting is pending and it's today - but less aggressively
        if ($meeting->status == MeetingStatus::Pending && $meeting->day == now()->format('Y-m-d'))
        {
            $this->pollTime = 30;
        }

        if ($meeting->status == MeetingStatus::InProgress) {
            $this->pollTime = 60;
        } elseif ($meeting->status == MeetingStatus::Finalizing) {
            $this->pollTime = 30;
        } elseif ($meeting->status == MeetingStatus::Completed) {
            $this->pollTime = 0;
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

        $this->pollTime = 60;
    }

    public function EndMeeting() {
        $this->authorize('update', $this->meeting);

        $this->modal('end-meeting-confirmation')->show();
    }

    public function CompleteMeeting() {
        $this->authorize('update', $this->meeting);

        // Add logging for debugging
        logger()->info('CompleteMeeting called', [
            'meeting_id' => $this->meeting->id,
            'status' => $this->meeting->status->value,
            'user_id' => auth()->id(),
        ]);

        try {
            $this->modal('complete-meeting-confirmation')->show();
        } catch (\Exception $e) {
            // Log the error and try to refresh the component
            logger()->error('Modal failed to open', [
                'error' => $e->getMessage(),
                'meeting_id' => $this->meeting->id,
            ]);

            // Force a component refresh
            $this->dispatch('$refresh');
        }
    }

    public function EndMeetingConfirmed() {
        $this->authorize('update', $this->meeting);

        // Add the general note and each department note to the minutes, if they exist. Use a heading for each section.
        $generalNote = $this->meeting->notes()->where('section_key', 'general')->first();
        if ($generalNote) {
            $this->meeting->minutes .= "## General Notes\n" . $generalNote->content . "\n\n";
        }

        foreach (StaffDepartment::cases() as $department) {
            $departmentNote = $this->meeting->notes()->where('section_key', $department->value)->first();
            if ($departmentNote) {
                $this->meeting->minutes .= "\n\n## " . $department->label() . " Notes\n" . $departmentNote->content;
            }
        }

        $note = MeetingNote::create([
            'meeting_id' => $this->meeting->id,
            'section_key' => 'community',
            'content' => $this->meeting->minutes,
            'created_by' => auth()->id(),
        ]);

        $this->meeting->endMeeting();

        $this->meeting->save();

        $this->modal('end-meeting-confirmation')->close();
    }

    public function CompleteMeetingConfirmed() {
        $this->authorize('update', $this->meeting);

        // Get the community note and save it to the meeting's community_minutes field
        $communityNote = $this->meeting->notes()->where('section_key', 'community')->first();
        if ($communityNote) {
            $this->meeting->community_minutes = $communityNote->content;
        }

        $this->meeting->completeMeeting();
        $this->meeting->save();

        $this->modal('complete-meeting-confirmation')->close();
    }
}; ?>

<div class="space-y-6">
    <div wire:poll.{{ $pollTime }}>
        <!-- Polling only affects this section -->
        <flux:heading size="xl" class="mb-6">{{  $meeting->title }} - {{  $meeting->day }}</flux:heading>

        <div class="block lg:flex gap-4">
            <flux:card class="w-full lg:w-1/2 mb-4 lg:mb-0">
                <flux:heading class="mb-4">Meeting Details</flux:heading>

                <flux:text>
                    <strong>Scheduled Time:</strong> {{ $meeting->scheduled_time->setTimezone('America/New_York')->format('F j, Y g:i A') }}<br>
                    <strong>Status:</strong> {{ $meeting->status->label() }}<br>
                    @if($meeting->start_time)
                        <strong>Start Time:</strong> {{ $meeting->start_time->setTimezone('America/New_York')->format('F j, Y g:i A') }} ET<br>
                    @endif
                    @if($meeting->attendees->count() > 0)
                        <strong>Attendees:</strong> {{ $meeting->attendees->count() }}<br>
                    @endif
                </flux:text>

                @if($meeting->attendees->count() > 0)
                    <div class="mt-4">
                        <flux:heading size="sm" class="mb-2">Attendees</flux:heading>
                        <div class="space-y-1">
                            @foreach($meeting->attendees as $attendee)
                                <div class="flex justify-between items-center text-sm">
                                    <span>
                                        <strong><flux:link href="{{ route('profile.show', $attendee) }}">{{ $attendee->name }}</flux:link></strong>
                                        @if($attendee->staff_rank && $attendee->staff_title)
                                            <br>
                                            <span class="text-gray-600 dark:text-gray-400 text-xs">
                                                {{ $attendee->staff_rank->label() }} - {{ $attendee->staff_title }}
                                            </span>
                                        @endif
                                    </span>
                                    <span class="text-gray-500 dark:text-gray-400 text-xs">
                                        @if(is_object($attendee->pivot->added_at))
                                            {{ $attendee->pivot->added_at->setTimezone('America/New_York')->format('g:i A') }}
                                        @else
                                            {{ $attendee->pivot->added_at }}
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($meeting->status->value === 'in_progress')
                    <div class="mt-4">
                        <livewire:meeting.manage-attendees :meeting="$meeting" :key="'attendees-' . $meeting->id" />
                    </div>
                @endif

            </flux:card>

            <div class="w-full lg:w-1/2">
                <flux:card class="w-full">
                    @if ($meeting->status == MeetingStatus::Pending)
                        <flux:heading variant="primary">Agenda</flux:heading>
                        <livewire:note.editor :meeting="$meeting" section_key="agenda"/>
                    @else
                        <div class="w-full mx-auto">
                            <flux:heading class="mb-4">Meeting Agenda</flux:heading>

                            <flux:text>{!! nl2br($meeting->agenda) !!}</flux:text>
                        </div>
                    @endif
                </flux:card>
            </div>
        </div>

        <div class="text-right w-full mt-6">
            @if ($this->meeting->status == MeetingStatus::Pending)
                <flux:button wire:click="StartMeeting" variant="primary">Start Meeting</flux:button>
            @endif
        </div>
    </div>

    @if ($meeting->status == MeetingStatus::InProgress)
        <livewire:meeting.department-section :meeting="$meeting" departmentValue="general" description="Notes not associated with any particular department." :key="'department-section-general'" />
        <flux:separator />

        @foreach(StaffDepartment::cases() as $department)
            <livewire:meeting.department-section :meeting="$meeting" :departmentValue="$department->value" description="" :key="'department-section-' . $department->value" />
            <flux:separator />
        @endforeach
    @elseif ($meeting->status == MeetingStatus::Finalizing)
        <div class="w-3/4 mx-auto">
            <flux:card>
                <flux:heading class="mb-4">Meeting Minutes</flux:heading>

                <flux:text>{!! nl2br($meeting->minutes) !!}</flux:text>
            </flux:card>
        </div>

        <livewire:meeting.department-section :meeting="$meeting" departmentValue="community" description="Sanitized notes that will be publicly viewable to all members." key="'department-section-community'" />
    @elseif ($meeting->status == MeetingStatus::Completed)
        <div class="w-3/4 mx-auto space-y-6">
            <flux:card>
                <flux:heading class="mb-4">Meeting Minutes</flux:heading>

                <flux:text>{!! nl2br($meeting->minutes) !!}</flux:text>
            </flux:card>

            @if($meeting->community_minutes)
                <flux:card>
                    <flux:heading class="mb-4">Community Notes</flux:heading>

                    <flux:text>{!! nl2br($meeting->community_minutes) !!}</flux:text>
                </flux:card>
            @endif
        </div>
    @endif

    <div class="w-full text-right">
        @if($meeting->status == MeetingStatus::InProgress)
            @can('update', $meeting)
                <flux:button wire:click="EndMeeting" variant="primary">End Meeting</flux:button>
            @endcan
            {{-- End Meeting Confirmation Modal --}}
            <flux:modal name="end-meeting-confirmation" class="min-w-[28rem] !text-left">
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
        @elseif($meeting->status == MeetingStatus::Finalizing)
            @can('update', $meeting)
                <flux:button
                    wire:click="CompleteMeeting"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50"
                    variant="primary"
                >
                    <span wire:loading.remove wire:target="CompleteMeeting">Complete Meeting</span>
                    <span wire:loading wire:target="CompleteMeeting">Loading...</span>
                </flux:button>
            @endcan
            {{-- Complete Meeting Confirmation Modal --}}
            <flux:modal name="complete-meeting-confirmation" class="min-w-[28rem] !text-left">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Complete Meeting?</flux:heading>

                        <flux:text class="mt-2">
                            You're about to complete this meeting and finalize all the notes.
                        </flux:text>
                        <flux:callout color="blue" class="mt-2">
                            <flux:callout.heading>Note:</flux:callout.heading>
                            <flux:callout.text>
                                Once completed, the meeting will be archived and no further changes can be made.
                            </flux:callout.text>
                        </flux:callout>
                    </div>

                    <div class="flex gap-2">
                        <flux:spacer />

                        <flux:modal.close>
                            <flux:button variant="ghost">Cancel</flux:button>
                        </flux:modal.close>

                        <flux:button wire:click="CompleteMeetingConfirmed" variant="primary">Complete Meeting</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    </div>
</div>
