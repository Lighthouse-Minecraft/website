<?php

use App\Models\Meeting;

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

describe('Meeting Create - Loading', function () {

    it('has a button on the meeting.index page that creates a meeting', function () {
        loginAsAdmin();

        get(route('meeting.index'))
            ->assertSeeLivewire('meeting.create-modal');
    })->done();

})->done(assignee: 'jonzenor', issue: 13);

describe('Meeting Create - Livewire Display Form Component', function () {

    it('loads the modal successfully', function () {
        loginAsAdmin();

        livewire('meeting.create-modal')
            ->assertSeeText('Create a Meeting');
    })->done();

    it('displays the form with required fields', function () {
        loginAsAdmin();

        livewire('meeting.create-modal')
            // Set the form fields data
            ->set('title', 'Test Meeting')
            ->set('day', '2025-04-04')
            ->set('time', '7:00 PM')

            // Make sure the form fields are displayed
            ->assertSee('Create a Meeting')
            ->assertSee('Meeting Title')
            ->assertSee('Meeting Date')

            // Check that the form fields were set correctly
            ->assertSet('title', 'Test Meeting')
            ->assertSet('day', '2025-04-04')
            ->assertSet('time', '7:00 PM')

            // Make sure required components are present
            ->assertSee('data-testid="meeting-create.store"', false);
    })->done();

})->done(assignee: 'jonzenor', issue: 13);

describe('Meeting Create - Functionality', function () {

    it('calculates the scheduled time based on day and time', function () {
        loginAsAdmin();

        livewire('meeting.create-modal')
            ->set('title', 'Test Meeting')
            ->set('day', '2025-04-04')
            ->set('time', '7:00 PM')
            ->call('CreateMeeting')
            ->assertSet('scheduled_time', '2025-04-04 23:00:00');
    })->wip();

    it('submits form data without error and saves to the database', function () {
        loginAsAdmin();

        livewire('meeting.create-modal')
            ->set('title', 'Test Meeting')
            ->set('day', '2025-04-04')
            ->set('time', '7:00 PM')
            ->call('createMeeting')
            ->assertEmitted('meeting.created');

        $this->assertDatabaseHas('meetings', [
            'title' => 'Test Meeting',
            'day' => '2025-04-04',
            'time' => '7:00 PM',
        ]);
    })->todo();

    it('validates user input')->todo();

    it('redirects to meeting.show after saving')->todo();

})->wip(assignee: 'jonzenor', issue: 13);

describe('Meeting Create - Permissions and Security', function () {
    // Handle page permissions
    // - Display a 404 instead of permission denied
    // - Members cannot view the page
    // - All officers can view the page
    // - Crew Members cannot view the page
    // - Jr Crew and above with the 'Meeting Secretary' role can view the page
})->todo(assignee: 'jonzenor', issue: 13);
