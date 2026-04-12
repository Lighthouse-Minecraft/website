<?php

declare(strict_types=1);

use App\Actions\PutUserInBrig;
use App\Actions\ReleaseUserFromBrig;
use App\Enums\DiscordAccountStatus;
use App\Enums\MembershipLevel;
use App\Models\DiscordAccount;
use App\Models\User;
use App\Services\DiscordApiService;
use App\Services\FakeDiscordApiService;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Notification;

uses()->group('discord', 'brig');

beforeEach(function () {
    $this->mock(MinecraftRconService::class)
        ->shouldReceive('executeCommand')
        ->andReturn(['success' => true, 'response' => null, 'error' => null]);
    app()->instance(DiscordApiService::class, new FakeDiscordApiService);
    Notification::fake();
});

it('sets discord accounts to brigged when user is put in brig', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $account = DiscordAccount::factory()->active()->create(['user_id' => $target->id]);

    PutUserInBrig::run($target, $admin, 'Test reason');

    expect($account->fresh()->status)->toBe(DiscordAccountStatus::Brigged);
});

it('calls removeAllManagedRoles when brigging', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $account = DiscordAccount::factory()->active()->create(['user_id' => $target->id]);

    // Bind as singleton so we can track calls
    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    PutUserInBrig::run($target, $admin, 'Test reason');

    $removeCalls = collect($fakeApi->calls)->where('method', 'removeAllManagedRoles');
    expect($removeCalls)->toHaveCount(1);
});

it('restores discord accounts to active when released from brig', function () {
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'in_brig' => true,
        'brig_reason' => 'Test',
    ]);
    $account = DiscordAccount::factory()->brigged()->create(['user_id' => $target->id]);

    ReleaseUserFromBrig::run($target, $admin, 'Appeal approved');

    expect($account->fresh()->status)->toBe(DiscordAccountStatus::Active);
});

it('syncs discord permissions when released from brig', function () {
    config(['lighthouse.discord.roles.traveler' => '111']);
    config(['lighthouse.discord.roles.verified' => '999']);

    $admin = User::factory()->create();
    $target = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'in_brig' => true,
        'brig_reason' => 'Test',
    ]);
    $account = DiscordAccount::factory()->brigged()->create(['user_id' => $target->id]);

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    ReleaseUserFromBrig::run($target, $admin, 'Appeal approved');

    $addCalls = collect($fakeApi->calls)->where('method', 'addRole');
    expect($addCalls->count())->toBeGreaterThanOrEqual(1);
});

it('assigns the In Brig Discord role when user is put in brig', function () {
    config(['lighthouse.discord.roles.in_brig' => 'BRIG_ROLE_ID']);

    $admin = User::factory()->create();
    $target = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $account = DiscordAccount::factory()->active()->create(['user_id' => $target->id]);

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    PutUserInBrig::run($target, $admin, 'Test reason');

    $addCalls = collect($fakeApi->calls)->where('method', 'addRole')->where('role_id', 'BRIG_ROLE_ID');
    expect($addCalls)->toHaveCount(1);
});

it('removes the In Brig Discord role when user is released from brig', function () {
    config(['lighthouse.discord.roles.in_brig' => 'BRIG_ROLE_ID']);

    $admin = User::factory()->create();
    $target = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'in_brig' => true,
        'brig_reason' => 'Test',
    ]);
    $account = DiscordAccount::factory()->brigged()->create(['user_id' => $target->id]);

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    ReleaseUserFromBrig::run($target, $admin, 'Appeal approved');

    $removeCalls = collect($fakeApi->calls)->where('method', 'removeRole')->where('role_id', 'BRIG_ROLE_ID');
    expect($removeCalls)->toHaveCount(1);
});

it('silently skips In Brig role sync when no Discord account is linked', function () {
    config(['lighthouse.discord.roles.in_brig' => 'BRIG_ROLE_ID']);

    $admin = User::factory()->create();
    $target = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    // No Discord account linked

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    PutUserInBrig::run($target, $admin, 'No Discord linked');

    $addCalls = collect($fakeApi->calls)->where('method', 'addRole')->where('role_id', 'BRIG_ROLE_ID');
    expect($addCalls)->toHaveCount(0);
});

it('does not assign In Brig role when config key is not set', function () {
    config(['lighthouse.discord.roles.in_brig' => null]);

    $admin = User::factory()->create();
    $target = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $account = DiscordAccount::factory()->active()->create(['user_id' => $target->id]);

    $fakeApi = new FakeDiscordApiService;
    app()->instance(DiscordApiService::class, $fakeApi);

    PutUserInBrig::run($target, $admin, 'No role configured');

    $addCalls = collect($fakeApi->calls)->where('method', 'addRole');
    expect($addCalls)->toHaveCount(0);
});
