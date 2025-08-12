<?php

use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

uses()->group('feature');

beforeEach(function () {
    //
});

describe('Meetings List Page - Load', function () {
    it('loads the Meetings List page for admins', function () {
        loginAsAdmin();

        get(route('meeting.index'))
            ->assertOk()
            ->assertViewIs('meeting.index')
            ->assertSee('Lighthouse Layout', false);
    });

    it('mounts the meetings list livewire component', function () {
        loginAsAdmin();

        get(route('meeting.index'))
            ->assertOk()
            ->assertSeeLivewire('meetings.list');
    });

    it('has a menu item for meetings', function () {
        loginAsAdmin();

        get(route('dashboard'))
            ->assertSee('Meeting Minutes')
            ->assertSee(route('meeting.index'));
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

describe('Meetings List Page - Permissions', function () {
    it('shows a 404 if an unauthorized person views the page', function () {
        get(route('meeting.index'))
            ->assertStatus(404);
    });

    it('does not allow guests to see the menu item for meetings', function () {
        get('/pages/home')
            ->assertDontSee('Meeting Minutes')
            ->assertDontSee(route('meeting.index'));
    });

    it('is not visible to Traveler members', function () {
        $user = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();
        actingAs($user);

        get(route('dashboard'))
            ->assertDontSee('Meeting Minutes')
            ->assertDontSee(route('meeting.index'));
    });

    it('is visible to Resident members', function () {
        $user = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create();
        actingAs($user);

        get(route('dashboard'))
            ->assertSee('Meeting Minutes')
            ->assertSee(route('meeting.index'));
    });

    it('is visible to Crew Members', function () {
        $user = User::factory()->withStaffPosition(StaffDepartment::Steward, StaffRank::CrewMember, 'crew')->create();
        actingAs($user);

        get(route('dashboard'))
            ->assertSee('Meeting Minutes')
            ->assertSee(route('meeting.index'));
    });

    it('does not show private meetings to members', function () {
        $privateMeeting = Meeting::factory()->private()->create();
        $user = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create();
        actingAs($user);

        get(route('meeting.index'))
            ->assertOk()
            ->assertDontSee($privateMeeting->day)
            ->assertDontSee(route('meeting.edit', $privateMeeting));
    });

    it('shows private meetings to officers', function () {
        $privateMeeting = Meeting::factory()->private()->create();
        $user = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)->create();
        actingAs($user);

        get(route('meeting.index'))
            ->assertOk()
            ->assertSee($privateMeeting->day)
            ->assertSee(route('meeting.edit', $privateMeeting));
    });

    it('allows members to view the page', function () {
        $meeting = Meeting::factory()->create();
        $user = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create();
        actingAs($user);

        get(route('meeting.edit', $meeting))
            ->assertOk()
            ->assertViewIs('meeting.edit');
    })->todo('move to ViewMeetingTest.php');

    it('shows 404 if a member tries to view a meeting they do not have permission for', function () {
        $meeting = Meeting::factory()->create();
        $user = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();
        actingAs($user);

        get(route('meeting.edit', $meeting))
            ->assertStatus(404);
    })->todo('move to ViewMeetingTest.php');
});

describe('Meetings List Page - Functionality', function () {
    it('links to the individual meeting pages', function () {
        $meeting = Meeting::factory()->create();
        loginAsAdmin();

        get(route('meeting.index'))
            ->assertSee($meeting->day)
            ->assertSee(route('meeting.edit', $meeting));
    });

    it('shows a message when there are no meetings', function () {
        loginAsAdmin();

        get(route('meeting.index'))
            ->assertSeeText('No meetings found');
    });

    it('shows a message when the only meeting is private and the user is not authorized', function () {
        $meeting = Meeting::factory()->private()->create();
        $user = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create();
        actingAs($user);

        get(route('meeting.index'))
            ->assertOk()
            ->assertSee('No meetings found');
    });

    it('shows a Create Meeting button for admins', function () {
        loginAsAdmin();

        get(route('meeting.index'))
            ->assertSee('Create Meeting')
            ->assertSeeLivewire('meeting.create-modal');
    })->done();
});
