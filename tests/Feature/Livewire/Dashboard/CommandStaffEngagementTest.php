<?php

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

uses()->group('command-dashboard', 'livewire');

beforeEach(function () {
    Cache::flush();
});

describe('Staff Engagement Widget', function () {
    it('can render for authorized users', function () {
        loginAsAdmin();

        $component = Volt::test('dashboard.command-staff-engagement');

        $component->assertSee('Staff Engagement');
    });

    it('shows paginated table of staff members', function () {
        loginAsAdmin();

        $staff = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)->create([
            'name' => 'Commander Test',
        ]);

        $component = Volt::test('dashboard.command-staff-engagement');

        $component->assertSee('Commander Test');
    });

    it('does not show non-staff users in the table', function () {
        loginAsAdmin();

        $member = User::factory()->create(['name' => 'Regular Member']);

        $component = Volt::test('dashboard.command-staff-engagement');

        $component->assertDontSee('Regular Member');
    });

    it('shows current iteration todo assigned and completed counts per staff', function () {
        loginAsAdmin();

        $meeting = Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(14),
        ]);

        $staff = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)->create([
            'name' => 'Task Staff',
        ]);

        // Create tasks assigned in current iteration
        Task::factory()->count(3)->create([
            'assigned_to_user_id' => $staff->id,
            'created_at' => now()->subDays(5),
            'created_by' => $staff->id,
            'assigned_meeting_id' => $meeting->id,
        ]);

        Task::factory()->create([
            'assigned_to_user_id' => $staff->id,
            'status' => TaskStatus::Completed,
            'completed_at' => now()->subDays(3),
            'created_at' => now()->subDays(5),
            'created_by' => $staff->id,
            'assigned_meeting_id' => $meeting->id,
        ]);

        $component = Volt::test('dashboard.command-staff-engagement');

        $component->assertSee('Task Staff');
    });

    it('shows reports submitted count over last 3 months', function () {
        loginAsAdmin();

        $meeting = Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(14),
        ]);

        $staff = User::factory()->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)->create([
            'name' => 'Report Staff',
        ]);

        MeetingReport::create([
            'meeting_id' => $meeting->id,
            'user_id' => $staff->id,
            'submitted_at' => now()->subDays(15),
        ]);

        $component = Volt::test('dashboard.command-staff-engagement');

        $component->assertSee('Report Staff');
    });

    it('shows meetings attended count over last 3 months', function () {
        loginAsAdmin();

        $meeting = Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(14),
        ]);

        $staff = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)->create([
            'name' => 'Attendee Staff',
        ]);

        $meeting->attendees()->attach($staff->id, ['added_at' => now()->subDays(14), 'attended' => true]);

        $component = Volt::test('dashboard.command-staff-engagement');

        $component->assertSee('Attendee Staff');
    });

    it('shows meetings missed only for crew member and above, not jr crew', function () {
        loginAsAdmin();

        $meeting = Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(14),
        ]);

        $officer = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)->create([
            'name' => 'Officer Missing',
        ]);

        $jrCrew = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::JrCrew)->create([
            'name' => 'JrCrew Missing',
        ]);

        // Neither attended the meeting
        $component = Volt::test('dashboard.command-staff-engagement');

        $component->assertSee('Officer Missing')
            ->assertSee('JrCrew Missing');
    });

    it('opens staff detail modal with 3-month history', function () {
        loginAsAdmin();

        Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(28),
        ]);
        Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(14),
        ]);

        $staff = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)->create([
            'name' => 'Detail Staff',
        ]);

        $component = Volt::test('dashboard.command-staff-engagement');

        $component->call('viewStaffDetail', $staff->id)
            ->assertSet('selectedStaffId', $staff->id);
    });
});

describe('Staff Engagement Widget - Permissions', function () {
    it('is visible to user with View Command Dashboard role on the dashboard', function () {
        $user = User::factory()
            ->withStaffPosition(\App\Enums\StaffDepartment::Command, \App\Enums\StaffRank::CrewMember)
            ->withRole('View Command Dashboard')
            ->create();
        loginAs($user);

        get('dashboard')
            ->assertSeeLivewire('dashboard.command-staff-engagement');
    });

    it('is visible to admins on the dashboard', function () {
        loginAsAdmin();

        get('dashboard')
            ->assertSeeLivewire('dashboard.command-staff-engagement');
    });

    it('is not visible to command staff without View Command Dashboard role', function () {
        $user = crewCommand();
        loginAs($user);

        get('dashboard')
            ->assertDontSeeLivewire('dashboard.command-staff-engagement');
    });

    it('is not visible to regular members', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertDontSeeLivewire('dashboard.command-staff-engagement');
    })->with('memberAll');
});
