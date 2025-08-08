<?php

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;
use App\Models\Meeting;
// use function Pest\Laravel\actingAs;

uses()->group('feature');

beforeEach(function () {
    $this->meetings = Meeting::factory()->count(3)->create();
});

describe('Meetings List Page - Load', function () {
    it('loads the Meetings List page for admins', function () {
        loginAsAdmin();

        get(route('meeting.index'))
            ->assertOk()
            ->assertViewIs('meeting.index');
    });

    it('mounts the meetings list livewire component', function () {
        loginAsAdmin();

        get(route('meeting.index'))
            ->assertOk()
            ->assertSeeLivewire('meetings.list');
    });
});

describe('Meeting List Page - Livewire Component', function () {
    it('loads okay', function () {
        loginAsAdmin();

        livewire('meetings.list')
            ->assertOk();
    });

    it('displays a list of meetings', function () {
        $meetings = Meeting::factory()->count(3)->create();
        loginAsAdmin();

        $component = livewire('meetings.list');

        foreach ($meetings as $meeting) {
            $component->assertSee($meeting->day);
        }
    });
});

describe('Meetings List Page - Permissions', function() {
    it('shows a 404 if an unauthorized person views the page', function () {
        get(route('meeting.index'))
            ->assertStatus(404);
    });

    it('allows members to view the page', function () {

    })->todo();
});

describe('Meetings List Page - Functionality', function () {
    it('links to the individual meeting pages', function () {

    })->todo();
})->todo();
