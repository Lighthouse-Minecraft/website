<?php

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Models\Meeting;
use App\Models\MeetingNote;
use Livewire\Volt\Component;

new class extends Component {
    #[\Livewire\Attributes\Computed]
    public function departmentNote()
    {
        $department = auth()->user()->staff_department;

        if (! $department) {
            return null;
        }

        $latestMeeting = Meeting::where('type', MeetingType::StaffMeeting)
            ->where('status', MeetingStatus::Completed)
            ->orderBy('end_time', 'desc')
            ->first();

        if (! $latestMeeting) {
            return [
                'meeting' => null,
                'content' => null,
                'department' => $department->label(),
            ];
        }

        $note = MeetingNote::where('meeting_id', $latestMeeting->id)
            ->where('section_key', $department->value)
            ->first();

        return [
            'meeting' => $latestMeeting,
            'content' => (! $note || empty(trim($note->content ?? ''))) ? null : $note->content,
            'department' => $department->label(),
        ];
    }
}; ?>

<div>
    @php $note = $this->departmentNote; @endphp

    @if($note)
        <flux:card>
            @if($note['meeting'] && $note['content'])
                <flux:heading class="mb-4">{{ $note['department'] }} Meeting Notes from {{ \Carbon\Carbon::parse($note['meeting']->day)->format('M j, Y') }} Staff Meeting</flux:heading>
                <flux:text>{!! nl2br(e($note['content'])) !!}</flux:text>
            @elseif($note['meeting'])
                <flux:heading class="mb-2">{{ $note['department'] }} Meeting Notes from {{ \Carbon\Carbon::parse($note['meeting']->day)->format('M j, Y') }} Staff Meeting</flux:heading>
                <flux:text variant="subtle" class="text-sm">No {{ $note['department'] }} notes were recorded for this meeting.</flux:text>
            @else
                <flux:heading class="mb-2">{{ $note['department'] }} Meeting Notes</flux:heading>
                <flux:text variant="subtle" class="text-sm">No completed staff meetings found.</flux:text>
            @endif
        </flux:card>
    @endif
</div>
