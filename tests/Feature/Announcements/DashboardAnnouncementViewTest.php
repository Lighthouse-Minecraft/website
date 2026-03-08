<?php

use App\Actions\AcknowledgeAnnouncement;
use App\Models\Announcement;
use Livewire\Livewire;

use function Pest\Laravel\get;

describe('Dashboard Display', function () {
    it('displays the newest unacknowledged announcement', function () {
        $announcement = Announcement::factory()->published()->create([
            'title' => 'Test Announcement',
            'notifications_sent_at' => now(),
        ]);
        loginAsAdmin();

        get(route('dashboard'))
            ->assertSeeLivewire('dashboard.view-announcements')
            ->assertSee($announcement->title);
    })->done();
})->done();

describe('Dashboard Display - Acknowledge Announcements', function () {
    it('does not display acknowledged announcements', function () {
        $announcement = Announcement::factory()->published()->create([
            'title' => 'Acknowledged Announcement',
            'notifications_sent_at' => now(),
        ]);
        $user = loginAsAdmin();
        AcknowledgeAnnouncement::run($announcement, $user);

        Livewire::test('dashboard.view-announcements')
            ->assertDontSee($announcement->title);
    })->done();
})->done(issue: 61, assignee: 'jonzenor');

describe('Dashboard Display - Announcements Widget', function () {
    it('loads the announcements widget component', function () {
        $user = loginAsAdmin();
        $user->update(['rules_accepted_at' => now()]);

        get(route('dashboard'))
            ->assertSeeLivewire('dashboard.announcements-widget')
            ->assertSee('Community Announcements');
    })->done();

    it('displays announcement titles in the widget', function () {
        $announcement1 = Announcement::factory()->published()->create([
            'title' => 'First Announcement',
            'notifications_sent_at' => now(),
        ]);
        $announcement2 = Announcement::factory()->published()->create([
            'title' => 'Second Announcement',
            'notifications_sent_at' => now(),
        ]);
        $user = loginAsAdmin();
        $user->update(['rules_accepted_at' => now()]);

        get(route('dashboard'))
            ->assertSeeLivewire('dashboard.announcements-widget')
            ->assertSee($announcement1->title)
            ->assertSee($announcement2->title);
    })->done();
})->done(issue: 62, assignee: 'jonzenor');
