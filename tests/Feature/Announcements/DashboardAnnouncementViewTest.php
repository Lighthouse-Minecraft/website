<?php

use App\Models\Announcement;
use App\Models\User;
use App\Actions\AcknowledgeAnnouncement;

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

describe('Dashboard Display - Acknowledge Announcements', function () {
    it('does not display acknowledged announcements', function () {
        $announcement = Announcement::factory()->create([
            'title' => 'Acknowledged Announcement',
            'content' => 'This announcement has been acknowledged.',
        ]);
        $user = loginAsAdmin();
        app(AcknowledgeAnnouncement::class)->run($announcement, $user);

        get(route('dashboard'))
            ->assertSeeLivewire('dashboard.view-announcements')
            ->assertDontSee($announcement->title)
            ->assertDontSee($announcement->content);
    })->done();
})->wip();
