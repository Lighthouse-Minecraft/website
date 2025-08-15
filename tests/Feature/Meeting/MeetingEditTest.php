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
    })->done();

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

})->done(assignee: 'jonzenor', issue: 13);

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

    // If the Meeting is in Finalized, do not show the department note editors
    it('does not show department note editors if the meeting is finalized', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create();
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertDontSee('Edit General')
            ->assertDontSee('Create General Note')
            ->assertDontSee('Edit Command')
            ->assertDontSee('Create Command Note')
            ->assertDontSee('Edit Chaplain')
            ->assertDontSee('Create Chaplain Note')
            ->assertDontSee('Edit Engineer')
            ->assertDontSee('Create Engineer Note')
            ->assertDontSee('Edit Quartermaster')
            ->assertDontSee('Create Quartermaster Note')
            ->assertDontSee('Edit Steward')
            ->assertDontSee('Create Steward Note');
    });

    // The End Meeting function copies all notes to the minutes_summary field of the meeting record
    it('copies all notes to the minutes summary when the meeting is finalized', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::InProgress)->create();
        $generalContent = 'Meeting General Content';
        $note = MeetingNote::factory()->create([
            'content' => $generalContent,
            'section_key' => 'general',
            'meeting_id' => $meeting->id,
        ]);
        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('EndMeetingConfirmed')
            ->assertSuccessful();

        $meeting->refresh();
        expect($meeting->minutes)->toContain($generalContent);
    })->done(assignee: 'jonzenor');

    // If the Meeting is in Finalizing, show the meeting minutes
    it('shows the meeting minutes if the meeting is finalizing', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)->withMinutes('This is a test of the minutes recording system.')->create();
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertSeeText('Meeting Minutes')
            ->assertSeeText($meeting->minutes);
    });

    // If the Meeting is in Finalizing, show the Community Notes section for public sanitized notes
    it('shows the community notes section if the meeting is finalizing', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create();
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertSee('Community')
            ->assertSee('Sanitized notes that will be publicly viewable to all members')
            ->assertSeeLivewire('meeting.department-section');
    });

    // The Community Notes section should allow editing during finalizing
    it('allows editing of community notes during finalizing', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create();
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertSeeLivewire('meeting.department-section')
            ->assertSee('Create Community Note');
    });

    // When ending a meeting, a community note should be created with the meeting minutes
    it('creates a community note with the meeting minutes when the meeting ends', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::InProgress)->create();
        $generalContent = 'General meeting notes';
        $commandContent = 'Command department notes';

        // Create some notes for different departments
        MeetingNote::factory()->create([
            'content' => $generalContent,
            'section_key' => 'general',
            'meeting_id' => $meeting->id,
        ]);

        MeetingNote::factory()->create([
            'content' => $commandContent,
            'section_key' => 'command',
            'meeting_id' => $meeting->id,
        ]);

        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('EndMeetingConfirmed')
            ->assertSuccessful();

        // Check that a community note was created
        $communityNote = MeetingNote::where('meeting_id', $meeting->id)
            ->where('section_key', 'community')
            ->first();

        expect($communityNote)->not->toBeNull();
        expect($communityNote->content)->toContain($generalContent);
        expect($communityNote->content)->toContain($commandContent);
        expect($communityNote->created_by)->toBe(auth()->id());
    });

    // If the Meeting is in a FFinalizing state, show a Complete Meeting button
    it('shows a Complete Meeting button if the meeting is finalizing', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create();
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertSee('Complete Meeting');
    })->done();

    // The Complete Meeting confirmation modal should be present on the page
    it('displays the complete meeting confirmation modal', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create();
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertSee('Complete Meeting?')
            ->assertSee('Once completed, the meeting will be archived')
            ->assertSee('Cancel');
    });

    // If the Complete Meeting button is pressed, show confirmation modal
    it('shows the complete meeting confirmation modal when the Complete Meeting button is pressed', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create();
        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('CompleteMeeting')
            ->assertSuccessful();
    });

    // If the Complete Meeting confirmation is confirmed, transition the meeting to Completed
    it('transitions the meeting to completed when the complete meeting is confirmed', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create();
        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('CompleteMeetingConfirmed')
            ->assertSuccessful();

        $meeting->refresh();
        expect($meeting->status)->toBe(MeetingStatus::Completed);
    });

    // When completing a meeting, the community note should be saved to community_minutes
    it('saves the community note to community_minutes when the meeting is completed', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create();
        $communityContent = 'These are the community meeting notes';

        $communityNote = MeetingNote::factory()->create([
            'content' => $communityContent,
            'section_key' => 'community',
            'meeting_id' => $meeting->id,
        ]);

        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('CompleteMeetingConfirmed')
            ->assertSuccessful();

        $meeting->refresh();
        expect($meeting->community_minutes)->toBe($communityContent);
    });

    // If the Meeting is Completed, do not show the Complete Meeting button
    it('does not show the Complete Meeting button if the meeting is completed', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Completed)->create();
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertDontSee('Complete Meeting');
    });

    // If the Meeting is Completed, do not show any note editors
    it('does not show any note editors if the meeting is completed', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Completed)->create();
        loginAsAdmin();

        get(route('meeting.edit', ['meeting' => $meeting->id]))
            ->assertDontSee('Create Community Note')
            ->assertDontSee('Edit Community')
            ->assertDontSeeLivewire('meeting.department-section');
    });

})->done(issue: 13, assignee: 'jonzenor');

// Next make a public page for viewing the completed meetings

// In the public page, if the user can view the full meeting minutes, have an option to switch between community and full
