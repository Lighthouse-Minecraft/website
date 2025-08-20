<?php

namespace App\Livewire\Meeting;

use App\Models\Meeting;
use App\Models\MeetingNote;
use Livewire\Component;

class NotesDisplay extends Component
{
    public $sectionKey;

    public $selectedMeetingId = null;

    public $selectedMeetingNote = null;

    public function mount($sectionKey)
    {
        $this->sectionKey = $sectionKey;
    }

    public function selectMeeting($meetingId)
    {
        $this->selectedMeetingId = $meetingId;

        // Find the meeting note for this meeting and section
        $this->selectedMeetingNote = MeetingNote::with(['meeting', 'createdBy'])
            ->where('meeting_id', $meetingId)
            ->where('section_key', $this->sectionKey)
            ->first();
    }

    public function getMeetingsProperty()
    {
        return Meeting::with('notes')
            ->whereHas('notes', function ($query) {
                $query->where('section_key', $this->sectionKey);
            })
            ->orderBy('scheduled_time', 'desc')
            ->get();
    }

    public function render()
    {
        return view('livewire.meeting.notes-display');
    }
}
