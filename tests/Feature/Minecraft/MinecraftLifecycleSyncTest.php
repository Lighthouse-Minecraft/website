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

test('SyncMinecraftPermissions sends whitelist add and rank for each active account', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'Player1']);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'Player2']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with(Mockery::pattern('/^whitelist add/'), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->twice()
        ->andReturn(['success' => true, 'response' => 'Added', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with(Mockery::pattern('/^lh setmember/'), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->twice()
        ->andReturn(['success' => true, 'response' => 'Success: rank set', 'error' => null]);

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

    // After promotion to Traveler the account is eligible — expect whitelist add + rank
    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist add PromoPlayer', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Added', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh setmember PromoPlayer traveler', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: rank set', 'error' => null]);

    PromoteUser::run($user);

    expect($user->fresh()->membership_level)->toBe(MembershipLevel::Traveler);
});

test('DemoteUser syncs Minecraft permissions through unified path', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Resident]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'DemotePlayer']);

    // After demotion to Traveler the account is still eligible
    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh setmember DemotePlayer traveler', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: rank set', 'error' => null]);

    DemoteUser::run($user);

    expect($user->fresh()->membership_level)->toBe(MembershipLevel::Traveler);
});

// ─── Reactivation ────────────────────────────────────────────────────────────

test('reactivation uses unified sync to restore whitelist and rank', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $account = MinecraftAccount::factory()->for($user)->removed()->create(['username' => 'ReactPlayer']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist add ReactPlayer', 'whitelist', 'ReactPlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Added ReactPlayer to the whitelist', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh setmember ReactPlayer traveler', 'rank', 'ReactPlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: rank set', 'error' => null]);

    $result = ReactivateMinecraftAccount::run($account, $user);

    expect($result['success'])->toBeTrue()
        ->and($account->fresh()->status)->toBe(MinecraftAccountStatus::Active);
});

test('reactivation reverts account to Removed if whitelist add fails', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $account = MinecraftAccount::factory()->for($user)->removed()->create(['username' => 'FailPlayer']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist add FailPlayer', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
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
        ->with('whitelist add BrigPlayer', 'whitelist', 'BrigPlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Added', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh setmember BrigPlayer traveler', 'rank', 'BrigPlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: rank set', 'error' => null]);

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

    // No lh rank/whitelist-add commands when parent blocks MC
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

test('parent enabling MC triggers whitelist add and rank sync via unified sync', function () {
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
        ->with('whitelist add ReEnabledKid', 'whitelist', 'ReEnabledKid', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Added', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh setmember ReEnabledKid traveler', 'rank', 'ReEnabledKid', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: rank set', 'error' => null]);

    UpdateChildPermission::run($child, $parent, 'minecraft', true);

    expect($child->fresh()->parent_allows_minecraft)->toBeTrue()
        ->and($account->fresh()->status)->toBe(MinecraftAccountStatus::Active);
});

test('parent enabling MC also syncs staff position when child is staff', function () {
    $parent = User::factory()->create();
    $child = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'parent_allows_minecraft' => false,
        'staff_department' => StaffDepartment::Engineer,
    ]);
    $account = MinecraftAccount::factory()->for($child)->create([
        'username' => 'StaffKid',
        'status' => MinecraftAccountStatus::ParentDisabled,
    ]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh setstaff StaffKid engineer', 'staff', 'StaffKid', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: staff set', 'error' => null]);

    UpdateChildPermission::run($child, $parent, 'minecraft', true);
});
