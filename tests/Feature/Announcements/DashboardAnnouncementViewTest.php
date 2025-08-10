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
    })->done();

})->done();

// Announcements are dismissable
