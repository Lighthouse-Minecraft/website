<?php

use App\Models\Announcement;

use function Pest\Laravel\get;

describe('Announcements do not break the dashboard', function () {
    it('loads the page without errors when there is a published announcement', function ($user) {
        loginAs($user);
        Announcement::factory()->published()->create(['notifications_sent_at' => now()]);

        get(route('dashboard'))
            ->assertOk();
    })->with('memberAll')->done();
})->done(issue: 71, assignee: 'jonzenor');
