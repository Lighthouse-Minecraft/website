<?php

use App\Enums\MeetingStatus;
use App\Enums\StaffDepartment;
use App\Models\Meeting;
use App\Models\MeetingNote;

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->meeting = Meeting::factory()->create([
        'title' => 'Test Meeting',
        'day' => '2025-05-04',
        'scheduled_time' => '2025-05-04 19:00:00',
    ]);
});

describe('Meeting Edit - Loading', function () {

    it('loads the page and relevant views', function () {
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $this->meeting->id]))
            ->assertOk()
            ->assertViewIs('meeting.edit')
            ->assertSee('Lighthouse Layout', false)
            ->assertViewHas('meeting', $this->meeting);
    })->done();

    // Loads model data
    it('loads model data', function () {
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $this->meeting->id]))
            ->assertSeeText($this->meeting->title)
            ->assertSeeText($this->meeting->day);
    })->done();

    it('displays an agenda edit textarea if the meeting has not started', function () {
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $this->meeting->id]))
            ->assertSee('Agenda')
            ->assertSeeLivewire('note.editor');
    })->wip();

    it('displays the agenda content if the meeting has started', function () {
        $this->meeting->update(['start_time' => now()]);
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $this->meeting->id]))
            ->assertSeeText($this->meeting->agenda)
            ->assertDontSeeLivewire('meeting.note-editor')
            ->assertSeeLivewire('meeting.note-viewer');
    })->todo();

})->done(assignee: 'jonzenor', issue: 56);

describe('Meeting Edit - Page Data', function () {
    // Handles invalid meeting
})->todo(assignee: 'jonzenor', issue: 56);

describe('Meeting Edit - Attendance', function () {
    // Shows the modal to select users for attendance

    // Shows users who attended the meeting
})->todo(assignee: 'jonzenor', issue: 55);

describe('Meeting Edit - Notes', function () {
    it('shows a section for each department', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::InProgress)->create();
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertSeeLivewire('meeting.department-section')
            ->assertSee('General')
            ->assertSee(StaffDepartment::Command->label())
            ->assertSee(StaffDepartment::Chaplain->label())
            ->assertSee(StaffDepartment::Engineer->label())
            ->assertSee(StaffDepartment::Quartermaster->label())
            ->assertSee(StaffDepartment::Steward->label());
    })->done();

})->wip(assignee: 'jonzenor', issue: 13);

describe('Meeting Edit - Action Items', function () {
    // Each department section has a todo list that can be added

    // The meeting should show existing todo items that are not completed

    // Have a button that imports outstanding tasks

    // Have a button that imports tasks completed since a selected meeting
})->todo(assignee: 'jonzenor', issue: 28);

describe('Meeting Edit - Permissions', function () {
    // Test permissions for the page
})->todo();

describe('Meeting Edit - Meeting Workflow', function () {
    // If the meeting is in a Pending state, show the Agenda editor
    it('displays the agenda editor if the meeting is in a pending state', function () {
        loginAsAdmin();
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Pending)->create();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertSee('Create Agenda');
    })->done(assignee: 'jonzenor', issue: 13);

    // If the meeting is in a Pending state, do not show the department sections
    it('does not show department sections if the meeting is in a pending state', function () {
        loginAsAdmin();
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Pending)->create();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertDontSee('General')
            ->assertDontSee(StaffDepartment::Command->label())
            ->assertDontSee(StaffDepartment::Chaplain->label())
            ->assertDontSee(StaffDepartment::Engineer->label())
            ->assertDontSee(StaffDepartment::Quartermaster->label())
            ->assertDontSee(StaffDepartment::Steward->label());
    })->done(assignee: 'jonzenor');

    // If the meeting is in a Pending state, show a button to Start the meeting
    it('shows a Start Meeting button if the meeting is in a pending state', function () {
        loginAsAdmin();
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Pending)->create();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertSee('Start Meeting');
    })->done(assignee: 'jonzenor');

    // If the Start Meeting button is pressed, transition the meeting to In Progress
    it('sets the meeting as started when the Start Meeting button is pressed', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Pending)->create();
        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('StartMeeting')
            ->assertSuccessful();

        $meeting->refresh();
        expect($meeting->status)->toBe(MeetingStatus::InProgress);
    })->done(assignee: 'jonzenor');

    // If the Start Meeting button is pressed, copy the Agenda notes to the meeting record
    it('copies the agenda notes to the meeting record when the Start Meeting button is pressed', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Pending)->create();
        $agendaContent = 'Try and take over the world!';
        $note = MeetingNote::factory()->create([
            'content' => $agendaContent,
            'section_key' => 'agenda',
            'meeting_id' => $meeting->id,
        ]);
        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('StartMeeting')
            ->assertSuccessful();

        $meeting->refresh();
        expect($meeting->agenda)->toBe($agendaContent);
    })->done(assignee: 'jonzenor');

    // If the Meeting is In Progress, do not show Agenda editor
    it('does not show the agenda editor if the meeting is in progress', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::InProgress)->create();
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertDontSee('Edit Agenda');
    })->done(assignee: 'jonzenor');

    it('shows the note viewer for the agenda if the meeting is in progress', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::InProgress)->create();
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertSee($meeting->agenda);
    })->done(assignee: 'jonzenor');

    // If the Meeting is In Progress, show the department note editors
    it('shows the department note editors when the meeting is in progress', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::InProgress)->create();
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertSee('Create General Note')
            ->assertSee('Create Command Note')
            ->assertSee('Create Chaplain Note')
            ->assertSee('Create Engineer Note')
            ->assertSee('Create Quartermaster Note')
            ->assertSee('Create Steward Note')
            ->assertSeeLivewire('note.editor');
    });

    // Hide the Start Meeting button if the meeting is In Progress
    it('hides the start button if the meeting is not Pending', function () {
        loginAsAdmin();
        $meeting = Meeting::factory()->withStatus(MeetingStatus::InProgress)->create();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertDontSee('Start Meeting');
    });

    // If the Meeting is In Progress, show an End Meeting button
    it('shows an End Meeting button if the meeting is in progress', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::InProgress)->create();
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertSee('End Meeting');
    })->done(assignee: 'jonzenor');

    // The End Meeting confirmation modal should be present on the page
    it('displays the end meeting confirmation modal', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::InProgress)->create();
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertSee('End Meeting?')
            ->assertSee('Once ended, the note fields will no longer be editable')
            ->assertSee('Cancel');
    })->done(assignee: 'jonzenor');

    // If the End Meeting button is pressed, confirm the action
    it('shows the end meeting confirmation modal when the End Meeting button is pressed', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::InProgress)->create();
        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('EndMeeting')
            ->assertSuccessful();
    })->done(assignee: 'jonzenor');

    // If the End Meeting confirmation is confirmed, transition the meeting to Finalizing
    it('transitions the meeting to finalizing when the end meeting is confirmed', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::InProgress)->create();
        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('EndMeetingConfirmed')
            ->assertSuccessful();

        $meeting->refresh();
        expect($meeting->status)->toBe(MeetingStatus::Finalizing);
        expect($meeting->end_time)->not->toBeNull();
    })->done(assignee: 'jonzenor');

    // If the Meeting is in a Finalizing state, do not show the End Meeting button
    it('does not show the End Meeting button if the meeting is in finalizing state', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create();
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertDontSee('End Meeting');
    });

    // If the End Meeting button is pressed, transition the meeting to Finalizing

    // If the End Meeting button is pressed, show all department notes sections

    // If the Meeting is in a Finalizing state, show a button to auto summarize the meeting

    // In Auto-Summarize send each department note to AI

    // The AI returns a summary of the meeting and stores it on the Meeting record

    // The AI returns a community sanitized version of each note and stores it with the repsective note record

    // If the Meeting is in a FInalizing state, show a Complete Meeting button

    // If the Complete Meeting button is pressed, change state to Closed

    // If the Meeting is in a Closed state, hide all department note editors

    // Allow editing after closing??

})->wip();

// Next make a public page for viewing the completed meetings

// In the public page, if the user can view the full meeting minutes, have an option to switch between community and full
