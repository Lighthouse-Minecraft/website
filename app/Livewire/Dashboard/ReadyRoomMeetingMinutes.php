<?php

namespace App\Livewire\Dashboard;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Enums\TaskStatus;
use App\Models\Meeting;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class ReadyRoomMeetingMinutes extends Component
{
    use WithPagination;

    public int $perPage = 10;

    public ?int $viewingMeetingId = null;
    public string $viewingModal = '';

    public function getMeetingsProperty()
    {
        return Meeting::where('type', MeetingType::StaffMeeting)
            ->where('status', MeetingStatus::Completed)
            ->withCount([
                'archivedTasks as archived_tasks_count' => fn ($q) => $q->where('status', TaskStatus::Archived),
            ])
            ->orderBy('scheduled_time', 'desc')
            ->paginate($this->perPage);
    }

    public function getViewingMeetingProperty()
    {
        if (! $this->viewingMeetingId) {
            return null;
        }

        return Meeting::with([
            'attendees' => fn ($q) => $q->orderByDesc('staff_rank')->orderBy('name'),
            'archivedTasks' => fn ($q) => $q->where('status', TaskStatus::Archived)->orderBy('section_key'),
        ])->find($this->viewingMeetingId);
    }

    public function showAttendance(int $meetingId): void
    {
        $this->viewingMeetingId = $meetingId;
        unset($this->viewingMeeting);
        Flux::modal('meeting-attendance-modal')->show();
    }

    public function showMinutes(int $meetingId): void
    {
        $this->viewingMeetingId = $meetingId;
        unset($this->viewingMeeting);
        Flux::modal('meeting-minutes-modal')->show();
    }

    public function showArchivedTasks(int $meetingId): void
    {
        $this->viewingMeetingId = $meetingId;
        unset($this->viewingMeeting);
        Flux::modal('meeting-archived-tasks-modal')->show();
    }

    public function render()
    {
        return view('livewire.dashboard.ready-room-meeting-minutes');
    }
}
