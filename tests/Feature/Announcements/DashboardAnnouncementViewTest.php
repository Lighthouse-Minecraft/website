<?php

use App\Models\Announcement;

use function Pest\Laravel\get;

describe('Dashboard display', function () {
    it('displays existing announcements', function () {
        $announcement = Announcement::factory()->create([
            'title' => 'Test Announcement',
            'content' => 'This is a test announcement.',
        ]);
        loginAsAdmin();

        get(route('dashboard'))
            ->assertSeeLivewire('dashboard.view-announcements')
            ->assertSee($announcement->title)
            ->assertSee($announcement->content);
    })->wip();

    it('does not display announcements if none exist', function () {
        loginAsAdmin();

        get(route('dashboard'))
            ->assertSeeLivewire('dashboard.view-announcements')
            ->assertDontSee('Announcements');
    })->wip();
})->wip();

// Announcements heading does not display if there are no announcements

// Announcements have a link to the full announcement

// Announcements are dismissable
