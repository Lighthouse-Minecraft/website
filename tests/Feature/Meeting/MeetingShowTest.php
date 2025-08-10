<?php

use App\Enums\StaffDepartment;
use App\Models\Meeting;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->meeting = Meeting::factory()->create([
        'title' => 'Test Meeting',
        'day' => '2025-05-04',
        'scheduled_time' => '2025-05-04 19:00:00',
    ]);
});

// TODO: RENAME THIS FEATURE TO USE MEETING EDIT

describe('Meeting Show - Loading', function () {

    it('loads the page and relevant views', function () {
        loginAsAdmin();

        get(route('meeting.show', ['meeting' => $this->meeting->id]))
            ->assertOk()
            ->assertViewIs('meeting.show')
            ->assertSee('Lighthouse Layout', false)
            ->assertViewHas('meeting', $this->meeting);
    })->done();

    // Loads model data
    it('loads model data', function () {
        loginAsAdmin();

        get(route('meeting.show', ['meeting' => $this->meeting->id]))
            ->assertSeeText($this->meeting->title)
            ->assertSeeText($this->meeting->day);
    })->done();

})->done(assignee: 'jonzenor', issue: 56);

describe('Meeting Show - Page Data', function () {
    // Handles invalid meeting
})->todo(assignee: 'jonzenor', issue: 56);

describe('Meeting Show - Attendance', function () {
    // Shows the modal to select users for attendance

    // Shows users who attended the meeting
})->todo(assignee: 'jonzenor', issue: 55);

describe('Meeting Show - Notes', function () {
    it('shows a section for each department', function () {
        loginAsAdmin();

        get(route('meeting.show', ['meeting' => $this->meeting->id]))
            ->assertSeeLivewire('meeting.department-section')
            ->assertSee('General')
            ->assertSee(StaffDepartment::Command->label())
            ->assertSee(StaffDepartment::Chaplain->label())
            ->assertSee(StaffDepartment::Engineer->label())
            ->assertSee(StaffDepartment::Quartermaster->label())
            ->assertSee(StaffDepartment::Steward->label());
    })->wip();

    // Show blank notes for each department section

    // Create the notes when data is added to the note
    // Saves the note sections rapidly (every few seconds while being worked on)
    // Locks the note section when the user starts editing
    // Unlocks the note section after the lock expires
    // Unlocks the note section when the user is done editing
})->wip(assignee: 'jonzenor', issue: 13);

describe('Meeting Show - Action Items', function () {
    // Each department section has a todo list that can be added

    // The meeting should show existing todo items that are not completed

    // Have a button that imports outstanding tasks

    // Have a button that imports tasks completed since a selected meeting
})->todo(assignee: 'jonzenor', issue: 28);

describe('Meeting Show - Permissions', function () {
    // Test permissions for the page
})->todo();
