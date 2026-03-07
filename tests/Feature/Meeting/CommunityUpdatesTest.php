<?php

use App\Enums\MeetingStatus;
use App\Models\Meeting;

use function Pest\Laravel\get;

describe('Community Updates Navigation page', function () {
    it('shows a link in the sidebar navigation for authenticated users', function () {
        loginAsAdmin();

        get('dashboard')
            ->assertSee('Community Updates')
            ->assertSee(route('community-updates.index'));
    });

    it('shows the link for all membership levels', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertSee('Community Updates')
            ->assertSee(route('community-updates.index'));
    })->with('memberAtLeastTraveler');

    it('shows the link for Stowaway and below', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertSee('Community Updates')
            ->assertSee(route('community-updates.index'));
    })->with('memberAtMostStowaway');

})->done(issue: 82, assignee: 'jonzenor');

describe('Community Updates page', function () {
    it('loads the Community Updates page', function () {
        loginAsAdmin();

        get(route('community-updates.index'))
            ->assertStatus(200)
            ->assertSee('Community Updates')
            ->assertViewIs('community-updates.index');
    });

    it('is accessible without authentication', function () {
        get(route('community-updates.index'))
            ->assertStatus(200)
            ->assertSee('Community Updates');
    });

    it('is accessible to all membership levels', function ($user) {
        loginAs($user);

        get(route('community-updates.index'))
            ->assertStatus(200)
            ->assertSee('Community Updates')
            ->assertViewIs('community-updates.index');
    })->with('memberAtMostStowaway');

})->done(issue: 82, assignee: 'jonzenor');

describe('Community Updates List', function () {
    it('lists finalized meetings with community updates for privileged users', function () {
        loginAsAdmin();
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Completed)->create();

        get(route('community-updates.index'))
            ->assertOk()
            ->assertSee($meeting->title)
            ->assertSee($meeting->day)
            ->assertSee($meeting->community_minutes);
    });

    it('shows only the latest update for guests', function () {
        $older = Meeting::factory()->withStatus(MeetingStatus::Completed)->create(['day' => now()->subDays(5)]);
        $newer = Meeting::factory()->withStatus(MeetingStatus::Completed)->create(['day' => now()->subDays(1)]);

        get(route('community-updates.index'))
            ->assertOk()
            ->assertSee($newer->title)
            ->assertDontSee($older->title)
            ->assertSee('Community members can see all past updates');
    });

    it('shows only the latest update for non-privileged users', function ($user) {
        loginAs($user);
        $older = Meeting::factory()->withStatus(MeetingStatus::Completed)->create(['day' => now()->subDays(5)]);
        $newer = Meeting::factory()->withStatus(MeetingStatus::Completed)->create(['day' => now()->subDays(1)]);

        get(route('community-updates.index'))
            ->assertOk()
            ->assertSee($newer->title)
            ->assertDontSee($older->title)
            ->assertSee('Community members can see all past updates');
    })->with('memberAtMostStowaway');

    it('shows all updates for privileged users', function ($user) {
        loginAs($user);
        $older = Meeting::factory()->withStatus(MeetingStatus::Completed)->create(['day' => now()->subDays(5)]);
        $newer = Meeting::factory()->withStatus(MeetingStatus::Completed)->create(['day' => now()->subDays(1)]);

        get(route('community-updates.index'))
            ->assertOk()
            ->assertSee($newer->title)
            ->assertSee($older->title)
            ->assertDontSee('Community members can see all past updates');
    })->with('memberAtLeastTraveler');

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

    it('paginates meetings with 10 per page for privileged users', function () {
        loginAsAdmin();

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

        foreach ($meetings->take(10) as $meeting) {
            $response->assertSee($meeting->title);
        }

        foreach ($meetings->skip(10) as $meeting) {
            $response->assertDontSee($meeting->title);
        }
    });

    it('shows empty state when no completed meetings exist', function () {
        loginAsAdmin();

        get(route('community-updates.index'))
            ->assertOk()
            ->assertSee('No community updates available');
    });

    it('renders accordion with first item expanded', function () {
        loginAsAdmin();
        $meetings = Meeting::factory()
            ->withStatus(MeetingStatus::Completed)
            ->count(3)
            ->create();

        $response = get(route('community-updates.index'))
            ->assertOk();

        $response->assertSee('ui-disclosure-group');
        $response->assertSee($meetings->sortByDesc('day')->first()->community_minutes);
    });

    it('does not show meetings with show_community_updates disabled', function () {
        loginAsAdmin();
        $hiddenMeeting = Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'show_community_updates' => false,
        ]);

        get(route('community-updates.index'))
            ->assertOk()
            ->assertDontSee($hiddenMeeting->title);
    });

    it('shows meetings with show_community_updates enabled', function () {
        loginAsAdmin();
        $visibleMeeting = Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'show_community_updates' => true,
        ]);

        get(route('community-updates.index'))
            ->assertOk()
            ->assertSee($visibleMeeting->title);
    });
})->done(issue: 82, assignee: 'jonzenor');
