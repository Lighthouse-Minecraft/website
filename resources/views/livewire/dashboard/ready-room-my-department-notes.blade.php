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
            return null;
        }

        $note = MeetingNote::where('meeting_id', $latestMeeting->id)
            ->where('section_key', $department->value)
            ->first();

        if (! $note || empty(trim($note->content ?? ''))) {
            return null;
        }

        return [
            'meeting' => $latestMeeting,
            'content' => $note->content,
            'department' => $department->label(),
        ];
    }
}; ?>

<div>
    @if($this->departmentNote)
        @php $note = $this->departmentNote; @endphp
        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <flux:heading>Most Recent {{ $note['department'] }} Meeting Notes</flux:heading>
                <flux:text variant="subtle" class="text-xs">{{ $note['meeting']->title }} &mdash; {{ \Carbon\Carbon::parse($note['meeting']->day)->format('M j, Y') }}</flux:text>
            </div>
            <flux:text>{!! nl2br(e($note['content'])) !!}</flux:text>
        </flux:card>
    @endif
</div>
