<?php

use App\Enums\MeetingStatus;
use App\Models\Meeting;

use function Pest\Laravel\get;

describe('Community Updates Navigation page', function () {
    // Show a link in the sidebar navigation
    it('shows a link in the sidebar navigation', function () {
        loginAsAdmin();

        get('dashboard')
            ->assertSee('Community Updates')
            ->assertSee(route('community-updates.index'));
    });

    // Traveler and above should see the updates navigation
    it('shows the updates navigation for Traveler and above', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertSee('Community Updates')
            ->assertSee(route('community-updates.index'));
    })->with('memberAtLeastTraveler');

    // Below traveler should not see the updates navigation
    it('does not show the updates navigation for Stowaway and below', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertDontSee('Community Updates')
            ->assertDontSee(route('community-updates.index'));
    })->with('memberAtMostStowaway');

})->done(issue: 82, assignee: 'jonzenor');

describe('Community Updates page', function () {
    // The Community Updates page loads
    it('loads the Community Updates page', function () {
        loginAsAdmin();

        get(route('community-updates.index'))
            ->assertStatus(200)
            ->assertSee('Community Updates')
            ->assertViewIs('community-updates.index');
    });

    // Traveler and above should have access to the page
    it('allows access to Traveler and above', function ($user) {
        loginAs($user);

        get(route('community-updates.index'))
            ->assertStatus(200)
            ->assertSee('Community Updates')
            ->assertViewIs('community-updates.index');
    })->with('memberAtLeastTraveler');

    // Below traveler should not have access to the page
    it('denies access to Stowaway and below', function ($user) {
        loginAs($user);

        get(route('community-updates.index'))
            ->assertStatus(403);
    })->with('memberAtMostStowaway');

})->done(issue: 82, assignee: 'jonzenor');

describe('Community Updates List', function () {
    // List meetings that have been finalized
    it('lists finalized meetings with community updates', function () {
        loginAsAdmin();
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Completed)->create();

        get(route('community-updates.index'))
            ->assertOk()
            ->assertSee($meeting->title)
            ->assertSee($meeting->day)
            ->assertSee($meeting->community_minutes);
    });

    // Do not show meetings that are any other status
    it('does not show meetings that are not completed', function () {
        loginAsAdmin();
        $meetingPending = Meeting::factory()->withStatus(MeetingStatus::Pending)->create();
        $meetingInProgress = Meeting::factory()->withStatus(MeetingStatus::InProgress)->create();
        $meetingFinalizing = Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create();

        get(route('community-updates.index'))
            ->assertOk()
            ->assertDontSee($meetingPending->title)
            ->assertDontSee($meetingPending->day)
            ->assertDontSee($meetingPending->community_minutes)
            ->assertDontSee($meetingInProgress->title)
            ->assertDontSee($meetingInProgress->day)
            ->assertDontSee($meetingInProgress->community_minutes)
            ->assertDontSee($meetingFinalizing->title)
            ->assertDontSee($meetingFinalizing->day)
            ->assertDontSee($meetingFinalizing->community_minutes);
    });

    // Pagination shows 10 meetings per page
    it('paginates meetings with 10 per page', function () {
        loginAsAdmin();

        // Create 15 meetings with distinct days to ensure proper ordering
        $meetings = collect();
        for ($i = 0; $i < 15; $i++) {
            $meetings->push(
                Meeting::factory()
                    ->withStatus(MeetingStatus::Completed)
                    ->create(['day' => now()->subDays($i)])
            );
        }

        $response = get(route('community-updates.index'))
            ->assertOk();

        // Should see first 10 meetings (most recent)
        foreach ($meetings->take(10) as $meeting) {
            $response->assertSee($meeting->title);
        }

        // Should not see meetings 11-15 on first page
        foreach ($meetings->skip(10) as $meeting) {
            $response->assertDontSee($meeting->title);
        }
    });

    // Shows empty state when no meetings exist
    it('shows empty state when no completed meetings exist', function () {
        loginAsAdmin();

        get(route('community-updates.index'))
            ->assertOk()
            ->assertSee('No community updates available');
    });

    // First meeting should be expanded by default
    it('renders accordion with first item expanded', function () {
        loginAsAdmin();
        $meetings = Meeting::factory()
            ->withStatus(MeetingStatus::Completed)
            ->count(3)
            ->create();

        $response = get(route('community-updates.index'))
            ->assertOk();

        // Verify accordion component structure is present
        $response->assertSee('ui-disclosure-group');

        // First meeting's content should be visible
        $response->assertSee($meetings->sortByDesc('day')->first()->community_minutes);
    });
})->done(issue: 82, assignee: 'jonzenor');
