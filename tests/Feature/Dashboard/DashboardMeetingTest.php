<?php

use App\Enums\MeetingStatus;
use App\Models\Meeting;

use function Pest\Laravel\get;

// Meetings in progress show up in the dashboard for officers and crew
it('meetings in progress show up in the dashboard for officers and crew', function ($user) {
    loginAs($user);
    $meeting = Meeting::factory()->withStatus(MeetingStatus::InProgress)->create();

    get(route('dashboard'))
        ->assertSee($meeting->title)
        ->assertSee(route('meeting.edit', $meeting));
})->with('rankAtLeastCrewMembers');

// Upcoming meetings show on the dashboard for officers and crew
