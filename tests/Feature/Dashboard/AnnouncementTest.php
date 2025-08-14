<?php

use App\Models\Announcement;

use function Pest\Laravel\get;

describe('Announcements do not break the dashboard', function () {
    it('loads the page without errors when there is a published announcement', function ($user) {
        loginAs($user);
        $announcement = Announcement::factory()->published()->create();

        get(route('dashboard'))
            ->assertOk();
    })->with('memberAll')->wip();
})->wip(issue: 71, assignee: 'jonzenor');
