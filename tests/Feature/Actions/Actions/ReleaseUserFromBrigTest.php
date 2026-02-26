<?php

declare(strict_types=1);

use App\Actions\ReleaseUserFromBrig;
use App\Enums\DiscordAccountStatus;
use App\Enums\EmailDigestFrequency;
use App\Enums\MembershipLevel;
use App\Enums\MinecraftAccountStatus;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\DiscordAccount;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Notifications\UserReleasedFromBrigNotification;
use App\Services\DiscordApiService;
use App\Services\FakeDiscordApiService;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Notification;

uses()->group('brig', 'actions');

beforeEach(function () {
    app()->instance(DiscordApiService::class, new FakeDiscordApiService);
});

it('marks the target user as no longer in brig', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Old reason',
        'brig_expires_at' => now()->addDays(10),
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    expect($target->fresh()->in_brig)->toBeFalse()
        ->and($target->fresh()->brig_reason)->toBeNull()
        ->and($target->fresh()->brig_expires_at)->toBeNull();
});

it('restores banned minecraft accounts to active', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create(['in_brig' => true]);
    $account = MinecraftAccount::factory()->create([
        'user_id' => $target->id,
        'status' => MinecraftAccountStatus::Banned,
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Pardoned');

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Active);
});

it('does not affect non-banned minecraft accounts', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create(['in_brig' => true]);
    $activeAccount = MinecraftAccount::factory()->active()->create(['user_id' => $target->id]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    expect($activeAccount->fresh()->status)->toBe(MinecraftAccountStatus::Active);
});

it('records activity for user released from brig', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create(['in_brig' => true]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Good behavior');

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $target->id,
        'action' => 'user_released_from_brig',
    ]);
});

it('sends notification to the released user', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'email_digest_frequency' => EmailDigestFrequency::Immediate,
        'notification_preferences' => ['tickets' => ['email' => true, 'pushover' => false]],
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    Notification::assertSentTo($target, UserReleasedFromBrigNotification::class);
});

it('clears next_appeal_available_at on release', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'next_appeal_available_at' => now()->addDays(5),
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    expect($target->fresh()->next_appeal_available_at)->toBeNull();
});

it('sets brig_timer_notified to false on release', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'brig_timer_notified' => true,
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    expect($target->fresh()->brig_timer_notified)->toBeFalse();
});

it('restores brigged discord accounts to active', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'membership_level' => MembershipLevel::Traveler,
    ]);
    $account = DiscordAccount::factory()->brigged()->create(['user_id' => $target->id]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    expect($account->fresh()->status)->toBe(DiscordAccountStatus::Active);
});

it('syncs discord roles on release', function () {
    config(['lighthouse.discord.roles.traveler' => '111']);
    config(['lighthouse.discord.roles.verified' => '999']);

    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'membership_level' => MembershipLevel::Traveler,
    ]);
    DiscordAccount::factory()->brigged()->create(['user_id' => $target->id]);

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    $syncCalls = collect($fakeApi->calls)->where('method', 'syncManagedRoles');
    expect($syncCalls->count())->toBeGreaterThanOrEqual(1);
});

it('syncs discord staff roles for staff users on release', function () {
    config(['lighthouse.discord.roles.staff_engineer' => '501']);
    config(['lighthouse.discord.roles.rank_crew_member' => '601']);

    $admin = User::factory()->create();
    $target = User::factory()
        ->withStaffPosition(StaffDepartment::Engineer, StaffRank::CrewMember)
        ->create(['in_brig' => true]);
    DiscordAccount::factory()->brigged()->create(['user_id' => $target->id]);

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    // Should have at least 2 syncManagedRoles calls: one for membership, one for staff
    $syncCalls = collect($fakeApi->calls)->where('method', 'syncManagedRoles');
    expect($syncCalls->count())->toBeGreaterThanOrEqual(2);
});

it('skips discord staff sync for non-staff users', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'staff_department' => null,
        'membership_level' => MembershipLevel::Traveler,
    ]);
    DiscordAccount::factory()->brigged()->create(['user_id' => $target->id]);

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    // Should have exactly 1 syncManagedRoles call (membership only, no staff)
    $syncCalls = collect($fakeApi->calls)->where('method', 'syncManagedRoles');
    expect($syncCalls->count())->toBe(1);
});

it('still records activity and sends notification even when no minecraft or discord accounts exist', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'email_digest_frequency' => EmailDigestFrequency::Immediate,
        'notification_preferences' => ['account' => ['email' => true, 'pushover' => false, 'discord' => false]],
    ]);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    ReleaseUserFromBrig::run($target, $admin, 'Released');

    $this->assertDatabaseHas('activity_logs', [
        'subject_id' => $target->id,
        'action' => 'user_released_from_brig',
    ]);

    Notification::assertSentTo($target, UserReleasedFromBrigNotification::class);
});
