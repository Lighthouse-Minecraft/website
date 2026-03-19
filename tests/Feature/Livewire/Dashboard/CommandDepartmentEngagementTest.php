<?php

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\ReportStatus;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Enums\TaskStatus;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\DisciplineReport;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\Task;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

uses()->group('command-dashboard', 'livewire');

beforeEach(function () {
    Cache::flush();
});

describe('Department Engagement Widget', function () {
    it('can render for authorized users', function () {
        loginAsAdmin();

        $component = Volt::test('dashboard.command-department-engagement');

        $component->assertSee('Department Engagement');
    });

    it('counts tickets opened in current iteration by department', function () {
        loginAsAdmin();

        Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(14),
        ]);

        Thread::factory()->count(2)->create([
            'type' => ThreadType::Ticket,
            'department' => StaffDepartment::Command,
            'status' => ThreadStatus::Open,
            'created_at' => now()->subDays(5),
        ]);

        $component = Volt::test('dashboard.command-department-engagement');

        $component->assertSee('Tickets / Todos by Department')
            ->assertSee('Command');
    });

    it('counts tickets remaining open', function () {
        loginAsAdmin();

        Thread::factory()->create([
            'type' => ThreadType::Ticket,
            'department' => StaffDepartment::Quartermaster,
            'status' => ThreadStatus::Open,
        ]);

        Thread::factory()->create([
            'type' => ThreadType::Ticket,
            'department' => StaffDepartment::Quartermaster,
            'status' => ThreadStatus::Closed,
        ]);

        $component = Volt::test('dashboard.command-department-engagement');

        $component->assertSee('Quartermaster');
    });

    it('counts todos created and completed by department', function () {
        loginAsAdmin();

        Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(14),
        ]);

        $user = User::factory()->create();
        Task::factory()->create([
            'section_key' => 'chaplain',
            'status' => TaskStatus::Pending,
            'created_at' => now()->subDays(5),
            'created_by' => $user->id,
        ]);

        Task::factory()->create([
            'section_key' => 'chaplain',
            'status' => TaskStatus::Completed,
            'completed_at' => now()->subDays(3),
            'created_at' => now()->subDays(5),
            'created_by' => $user->id,
        ]);

        $component = Volt::test('dashboard.command-department-engagement');

        $component->assertSee('Chaplain');
    });

    it('shows discipline reports published count', function () {
        loginAsAdmin();

        DisciplineReport::factory()->create([
            'status' => ReportStatus::Published,
            'published_at' => now()->subDays(3),
        ]);

        $component = Volt::test('dashboard.command-department-engagement');

        $component->assertSee('Discipline Reports Published');
    });

    it('shows draft reports count with attention badge', function () {
        loginAsAdmin();

        DisciplineReport::factory()->create([
            'status' => ReportStatus::Draft,
        ]);

        $component = Volt::test('dashboard.command-department-engagement');

        $component->assertSee('Reports in Draft')
            ->assertSee('pending review');
    });

    it('shows staff report completion percentage for previous meeting', function () {
        loginAsAdmin();

        $meeting1 = Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(28),
        ]);
        $meeting2 = Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(14),
        ]);

        // Create staff members
        $staff1 = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)->create();
        $staff2 = User::factory()->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)->create();

        // Only staff1 submitted report for meeting2
        MeetingReport::create([
            'meeting_id' => $meeting2->id,
            'user_id' => $staff1->id,
            'submitted_at' => now()->subDays(15),
        ]);

        $component = Volt::test('dashboard.command-department-engagement');

        $component->assertSee('Staff Report Completion');
    });

    it('shows meeting attendance percentage excluding jr crew', function () {
        loginAsAdmin();

        $meeting1 = Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(28),
        ]);
        $meeting2 = Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(14),
        ]);

        $officer = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)->create();
        $jrCrew = User::factory()->withStaffPosition(StaffDepartment::Command, StaffRank::JrCrew)->create();

        // Both attend meeting2
        $meeting2->attendees()->attach([$officer->id, $jrCrew->id], ['added_at' => now()->subDays(14), 'attended' => true]);

        $component = Volt::test('dashboard.command-department-engagement');

        $component->assertSee('Meeting Attendance');
    });

    it('shows dashes when no previous meeting exists', function () {
        loginAsAdmin();

        $component = Volt::test('dashboard.command-department-engagement');

        $component->assertSee('No completed meeting');
    });

    it('opens detail modal for discipline timeline', function () {
        loginAsAdmin();

        Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(28),
        ]);
        Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(14),
        ]);

        $component = Volt::test('dashboard.command-department-engagement');

        $component->call('showDetail', 'discipline')
            ->assertSet('activeDetailMetric', 'discipline');
    });
});

describe('Department Engagement Widget - Permissions', function () {
    it('is visible to user with View Command Dashboard role on the dashboard', function () {
        $user = User::factory()
            ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
            ->withRole('View Command Dashboard')
            ->create();
        loginAs($user);

        get('dashboard')
            ->assertSeeLivewire('dashboard.command-department-engagement');
    });

    it('is visible to admins on the dashboard', function () {
        loginAsAdmin();

        get('dashboard')
            ->assertSeeLivewire('dashboard.command-department-engagement');
    });

    it('is not visible to command staff without View Command Dashboard role', function () {
        $user = officerCommand();
        loginAs($user);

        get('dashboard')
            ->assertDontSeeLivewire('dashboard.command-department-engagement');
    });

    it('is not visible to regular members', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertDontSeeLivewire('dashboard.command-department-engagement');
    })->with('memberAll');
});
