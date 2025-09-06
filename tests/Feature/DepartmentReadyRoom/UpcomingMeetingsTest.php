<?php

use App\Models\Meeting;

use function Pest\Laravel\get;

beforeEach(function () {
    // Set up necessary data and state
    $this->department = 'command';
    $this->meeting = Meeting::factory()->create([
        'scheduled_time' => now()->addDays(10),
        'status' => 'pending',
    ]);
});

describe('Upcoming Meetings Widget', function () {

    it('displays the upcoming meetings widget', function () {
        loginAsAdmin();

        // Visit the dashboard page
        get(route('ready-room.index'))
            ->assertStatus(200)
            ->assertSee('Upcoming Meetings')
            ->assertSeeLivewire('dashboard.ready-room-upcoming-meetings');
    });

    it('shows the next meeting in the widget', function () {
        loginAsAdmin();

        // Visit the dashboard page
        get(route('ready-room.index'))
            ->assertStatus(200)
            ->assertSee($this->meeting->title)
            ->assertSee(route('meeting.edit', $this->meeting))
            ->assertSee($this->meeting->scheduled_time->format('m/d/Y \@ g:i a').' ET');
    });
})->done(issue: 170, assignee: 'jonzenor');
