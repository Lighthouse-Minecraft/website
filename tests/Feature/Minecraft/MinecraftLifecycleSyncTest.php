<?php

declare(strict_types=1);

use App\Actions\DemoteUser;
use App\Actions\PromoteUser;
use App\Actions\ReactivateMinecraftAccount;
use App\Actions\ReleaseUserFromBrig;
use App\Actions\SyncMinecraftPermissions;
use App\Actions\UpdateChildPermission;
use App\Enums\BrigType;
use App\Enums\MembershipLevel;
use App\Enums\MinecraftAccountStatus;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Services\MinecraftRconService;

beforeEach(function () {
    $this->rconMock = $this->mock(MinecraftRconService::class);
    $this->rconMock->shouldReceive('executeCommand')
        ->andReturn(['success' => true, 'response' => 'ok', 'error' => null])
        ->byDefault();
});

// ─── SyncMinecraftPermissions ─────────────────────────────────────────────────

test('SyncMinecraftPermissions sends lh syncuser for each active account', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'Player1']);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'Player2']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with(Mockery::pattern('/^lh syncuser Player1 /'), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced Player1', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with(Mockery::pattern('/^lh syncuser Player2 /'), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced Player2', 'error' => null]);

    SyncMinecraftPermissions::run($user);
});

test('SyncMinecraftPermissions skips removed and verifying accounts', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($user)->removed()->create(['username' => 'OldPlayer']);
    MinecraftAccount::factory()->for($user)->verifying()->create(['username' => 'NewPlayer']);

    $this->rconMock->shouldReceive('executeCommand')->never();

    SyncMinecraftPermissions::run($user);
});

// ─── Promotion / Demotion ─────────────────────────────────────────────────────

test('PromoteUser syncs Minecraft permissions through unified path', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'PromoPlayer']);

    // After promotion to Traveler the account is eligible — expect lh syncuser
    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser PromoPlayer traveler none', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced PromoPlayer', 'error' => null]);

    PromoteUser::run($user);

    expect($user->fresh()->membership_level)->toBe(MembershipLevel::Traveler);
});

test('DemoteUser syncs Minecraft permissions through unified path', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Resident]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'DemotePlayer']);

    // After demotion to Traveler the account is still eligible
    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser DemotePlayer traveler none', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced DemotePlayer', 'error' => null]);

    DemoteUser::run($user);

    expect($user->fresh()->membership_level)->toBe(MembershipLevel::Traveler);
});

// ─── Reactivation ────────────────────────────────────────────────────────────

test('reactivation uses unified sync to restore whitelist and rank', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $account = MinecraftAccount::factory()->for($user)->removed()->create(['username' => 'ReactPlayer']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser ReactPlayer traveler none', 'sync', 'ReactPlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced ReactPlayer', 'error' => null]);

    $result = ReactivateMinecraftAccount::run($account, $user);

    expect($result['success'])->toBeTrue()
        ->and($account->fresh()->status)->toBe(MinecraftAccountStatus::Active);
});

test('reactivation reverts account to Removed if sync fails', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $account = MinecraftAccount::factory()->for($user)->removed()->create(['username' => 'FailPlayer']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with(Mockery::pattern('/^lh syncuser FailPlayer /'), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->andReturn(['success' => false, 'response' => null, 'error' => 'Server unreachable']);

    $result = ReactivateMinecraftAccount::run($account, $user);

    expect($result['success'])->toBeFalse()
        ->and($account->fresh()->status)->toBe(MinecraftAccountStatus::Removed);
});

// ─── Brig release ────────────────────────────────────────────────────────────

test('brig release restores Minecraft access via unified sync when parent allows', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'in_brig' => false,
        'parent_allows_minecraft' => true,
    ]);
    $account = MinecraftAccount::factory()->for($user)->create([
        'username' => 'BrigPlayer',
        'status' => MinecraftAccountStatus::Banned,
    ]);

    // Simulate brig state
    $user->in_brig = true;
    $user->brig_reason = 'Test';
    $user->brig_type = BrigType::Discipline;
    $user->save();

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser BrigPlayer traveler none', 'sync', 'BrigPlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced BrigPlayer', 'error' => null]);

    ReleaseUserFromBrig::run($user, $admin, 'Testing release', notify: false);

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Active);
});

test('brig release sets account to ParentDisabled and skips sync when parent blocks MC', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'in_brig' => false,
        'parent_allows_minecraft' => false,
    ]);
    $account = MinecraftAccount::factory()->for($user)->create([
        'username' => 'BlockedKid',
        'status' => MinecraftAccountStatus::Banned,
    ]);

    $user->in_brig = true;
    $user->brig_reason = 'Test';
    $user->brig_type = BrigType::Discipline;
    $user->save();

    // No lh syncuser or whitelist-add when parent blocks MC
    $this->rconMock->shouldReceive('executeCommand')
        ->with(Mockery::pattern('/^lh |^whitelist add/'), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->never();

    ReleaseUserFromBrig::run($user, $admin, 'Testing release', notify: false);

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::ParentDisabled);
});

// ─── Parent permission toggle ─────────────────────────────────────────────────

test('parent disabling MC triggers whitelist remove via unified sync', function () {
    $parent = User::factory()->create();
    $child = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'parent_allows_minecraft' => true,
    ]);
    $account = MinecraftAccount::factory()->for($child)->active()->create(['username' => 'ChildPlayer']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist remove ChildPlayer', 'whitelist', 'ChildPlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Removed', 'error' => null]);

    UpdateChildPermission::run($child, $parent, 'minecraft', false);

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::ParentDisabled);
});

test('parent enabling MC triggers lh syncuser via unified sync', function () {
    $parent = User::factory()->create();
    $child = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'parent_allows_minecraft' => false,
    ]);
    $account = MinecraftAccount::factory()->for($child)->create([
        'username' => 'ReEnabledKid',
        'status' => MinecraftAccountStatus::ParentDisabled,
    ]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser ReEnabledKid traveler none', 'sync', 'ReEnabledKid', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced ReEnabledKid', 'error' => null]);

    UpdateChildPermission::run($child, $parent, 'minecraft', true);

    expect($child->fresh()->parent_allows_minecraft)->toBeTrue()
        ->and($account->fresh()->status)->toBe(MinecraftAccountStatus::Active);
});

test('parent enabling MC syncs staff position for Officer child via lh syncuser', function () {
    $parent = User::factory()->create();
    $child = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'parent_allows_minecraft' => false,
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Engineer,
    ]);
    $account = MinecraftAccount::factory()->for($child)->create([
        'username' => 'StaffKid',
        'status' => MinecraftAccountStatus::ParentDisabled,
    ]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser StaffKid traveler engineer', 'sync', 'StaffKid', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced StaffKid', 'error' => null]);

    UpdateChildPermission::run($child, $parent, 'minecraft', true);
});
