<?php

use App\Actions\AcknowledgeAnnouncement;
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
            // We can't check for announcement title because it will still show up in the widget
            ->assertDontSee($announcement->content);
    })->done();
})->wip(issue: 61, assignee: 'jonzenor');

describe('Dashboard Display - Announcements List', function () {

    it('loads the announcements list component', function () {
        loginAsAdmin();

        get(route('dashboard'))
            ->assertSeeLivewire('dashboard.announcements-widget')
            ->assertSee('START OF ANNOUNCEMENTS WIDGET')
            ->assertSee('END OF ANNOUNCEMENTS WIDGET');
    })->done();

    it('displays a list of announcements in a widget', function () {
        $announcement1 = Announcement::factory()->create([
            'title' => 'First Announcement',
            'content' => 'Content of the first announcement.',
        ]);
        $announcement2 = Announcement::factory()->create([
            'title' => 'Second Announcement',
            'content' => 'Content of the second announcement.',
        ]);
        loginAsAdmin();

        get(route('dashboard'))
            ->assertSeeLivewire('dashboard.view-announcements')
            ->assertSeeInOrder(['START OF ANNOUNCEMENTS WIDGET', $announcement1->title, 'END OF ANNOUNCEMENTS WIDGET'])
            ->assertSeeInOrder(['START OF ANNOUNCEMENTS WIDGET', $announcement2->title, 'END OF ANNOUNCEMENTS WIDGET']);
    })->wip();
})->wip(issue: 62, assignee: 'jonzenor');
