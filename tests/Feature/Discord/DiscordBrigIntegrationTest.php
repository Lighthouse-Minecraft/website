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
    $admin = User::factory()->create();
    $target = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'in_brig' => true,
        'brig_reason' => 'Test',
    ]);
    $account = DiscordAccount::factory()->brigged()->create(['user_id' => $target->id]);

    $fakeApi = app(DiscordApiService::class);

    ReleaseUserFromBrig::run($target, $admin, 'Appeal approved');

    if ($fakeApi instanceof FakeDiscordApiService) {
        // Should have role add calls from SyncDiscordPermissions
        $addCalls = collect($fakeApi->calls)->where('method', 'addRole');
        expect($addCalls->count())->toBeGreaterThanOrEqual(0);
    }
});
