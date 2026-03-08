<?php

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\MinecraftAccountStatus;
use App\Models\DiscordAccount;
use App\Models\Meeting;
use App\Models\MinecraftAccount;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

uses()->group('command-dashboard', 'livewire');

beforeEach(function () {
    Cache::flush();
});

describe('Community Engagement Widget', function () {
    it('can render for authorized users', function () {
        loginAsAdmin();

        $component = Volt::test('dashboard.command-community-engagement');

        $component->assertSee('Community Engagement');
    });

    it('counts new users created in the current iteration', function () {
        loginAsAdmin();

        // Create iteration boundary
        Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(14),
        ]);

        // Create users in current iteration
        User::factory()->count(3)->create(['created_at' => now()->subDays(5)]);

        $component = Volt::test('dashboard.command-community-engagement');

        // The admin user created by loginAsAdmin was also created recently
        $component->assertSee('New Users');
    });

    it('counts new minecraft accounts in the current iteration', function () {
        loginAsAdmin();

        Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(14),
        ]);

        $user = User::factory()->create();
        MinecraftAccount::factory()->count(2)->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(5),
        ]);

        $component = Volt::test('dashboard.command-community-engagement');

        $component->assertSee('New MC Accounts');
    });

    it('counts pending minecraft verification accounts', function () {
        loginAsAdmin();

        $user = User::factory()->create();
        MinecraftAccount::factory()->create([
            'user_id' => $user->id,
            'status' => MinecraftAccountStatus::Verifying,
        ]);

        $component = Volt::test('dashboard.command-community-engagement');

        $component->assertSee('Pending MC Verification')
            ->assertSee('needs attention');
    });

    it('counts new discord accounts in the current iteration', function () {
        loginAsAdmin();

        Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(14),
        ]);

        $user = User::factory()->create();
        DiscordAccount::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(5),
        ]);

        $component = Volt::test('dashboard.command-community-engagement');

        $component->assertSee('New Discord Accounts');
    });

    it('shows active users metric', function () {
        loginAsAdmin();

        $component = Volt::test('dashboard.command-community-engagement');

        $component->assertSee('Active Users');
    });

    it('opens detail modal', function () {
        loginAsAdmin();

        Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(28),
        ]);
        Meeting::factory()->withStatus(MeetingStatus::Completed)->create([
            'type' => MeetingType::StaffMeeting,
            'end_time' => now()->subDays(14),
        ]);

        $component = Volt::test('dashboard.command-community-engagement');

        $component->call('showDetail', 'new_users')
            ->assertSet('activeDetailMetric', 'new_users');
    });
});

describe('Community Engagement Widget - Permissions', function () {
    it('is visible to command department staff on the dashboard', function () {
        $user = crewCommand();
        loginAs($user);

        get('dashboard')
            ->assertSee('Command Staff')
            ->assertSeeLivewire('dashboard.command-community-engagement');
    });

    it('is visible to admins on the dashboard', function () {
        loginAsAdmin();

        get('dashboard')
            ->assertSee('Command Staff')
            ->assertSeeLivewire('dashboard.command-community-engagement');
    });

    it('is visible to command department jr crew', function () {
        $user = jrCrewCommand();
        loginAs($user);

        get('dashboard')
            ->assertSee('Command Staff')
            ->assertSeeLivewire('dashboard.command-community-engagement');
    });

    it('is not visible to non-command officers', function () {
        $user = officerChaplain();
        loginAs($user);

        get('dashboard')
            ->assertDontSeeLivewire('dashboard.command-community-engagement');
    });

    it('is not visible to non-command crew members', function () {
        $user = crewChaplain();
        loginAs($user);

        get('dashboard')
            ->assertDontSeeLivewire('dashboard.command-community-engagement');
    });

    it('is not visible to regular members', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertDontSeeLivewire('dashboard.command-community-engagement');
    })->with('memberAll');
});
