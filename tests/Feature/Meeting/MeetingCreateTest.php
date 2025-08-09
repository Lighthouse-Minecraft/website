<?php

use App\Models\Meeting;

use function Pest\Laravel\get;

describe('Meeting Create - Loading', function () {

    it('has a button on the meeting.index page that creates a meeting', function () {
        loginAsAdmin();
        $meeting = Meeting::factory()->create();

        get(route('meeting.index'))
            ->assertSeeLivewire('meeting-create-modal');
    })->wip();

    it('loads successfully with its view', function () {
        loginAsAdmin();

    })->todo();

})->wip(assignee: 'jonzenor', issue: 13);

describe('Meeting Create - Livewire Display Form Component', function () {

    it('includes the livewire Create Meeting modal')->todo();

    it('includes a submit button on the form')->todo();

})->todo(assignee: 'jonzenor', issue: 13);

describe('Meeting Create - Functionality', function () {

    it('submits form data without error')->todo();

    it('saves the form data')->todo();

    it('validates user input')->todo();

    it('redirects to meeting.show after saving')->todo();

})->todo(assignee: 'jonzenor', issue: 13);

describe('Meeting Create - Permissions and Security', function () {
    // Handle page permissions
    // - Display a 404 instead of permission denied
    // - Members cannot view the page
    // - All officers can view the page
    // - Crew Members cannot view the page
    // - Jr Crew and above with the 'Meeting Secretary' role can view the page
})->todo(assignee: 'jonzenor', issue: 13);
