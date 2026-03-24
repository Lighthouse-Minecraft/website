<?php

declare(strict_types=1);

use App\Actions\SyncMinecraftAccount;
use App\Enums\MembershipLevel;
use App\Enums\MinecraftAccountType;
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

// ─── Eligible user (Traveler+, not in brig, parent allows) ───────────────────

test('eligible user receives whitelist add, rank set, and staff removed', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $account = MinecraftAccount::factory()->for($user)->active()->create(['username' => 'Steve']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist add Steve', 'whitelist', 'Steve', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Added Steve to whitelist', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh setmember Steve traveler', 'rank', 'Steve', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: rank set', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh removestaff Steve', 'staff', 'Steve', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: staff removed', 'error' => null]);

    $result = SyncMinecraftAccount::run($account);

    expect($result['eligible'])->toBeTrue()
        ->and($result['whitelist']['action'])->toBe('add')
        ->and($result['whitelist']['success'])->toBeTrue()
        ->and($result['rank']['rank'])->toBe('traveler')
        ->and($result['staff']['action'])->toBe('remove');
});

test('eligible staff user receives setstaff command', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'staff_department' => StaffDepartment::Engineer,
    ]);
    $account = MinecraftAccount::factory()->for($user)->active()->create(['username' => 'StaffPlayer']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh setstaff StaffPlayer engineer', 'staff', 'StaffPlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: staff set', 'error' => null]);

    $result = SyncMinecraftAccount::run($account);

    expect($result['eligible'])->toBeTrue()
        ->and($result['staff']['action'])->toBe('set')
        ->and($result['staff']['department'])->toBe('engineer');
});

test('eligible user records rank sync and staff activity', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $account = MinecraftAccount::factory()->for($user)->active()->create(['username' => 'Steve']);

    SyncMinecraftAccount::run($account);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'action' => 'minecraft_rank_synced',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'action' => 'minecraft_staff_position_removed',
    ]);
});

test('eligible staff user records staff position set activity', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'staff_department' => StaffDepartment::Chaplain,
    ]);
    $account = MinecraftAccount::factory()->for($user)->active()->create(['username' => 'Padre']);

    SyncMinecraftAccount::run($account);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'action' => 'minecraft_staff_position_set',
    ]);
});

// ─── Ineligible: below server access threshold ───────────────────────────────

test('user below membership threshold receives whitelist remove', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    $account = MinecraftAccount::factory()->for($user)->active()->create(['username' => 'NewGuy']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist remove NewGuy', 'whitelist', 'NewGuy', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Removed', 'error' => null]);

    $result = SyncMinecraftAccount::run($account);

    expect($result['eligible'])->toBeFalse()
        ->and($result['whitelist']['action'])->toBe('remove')
        ->and($result['rank'])->toBeNull()
        ->and($result['staff'])->toBeNull();
});

test('ineligible user does not receive rank or staff commands', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Drifter]);
    $account = MinecraftAccount::factory()->for($user)->active()->create(['username' => 'Drifter1']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with(Mockery::pattern('/^lh /'), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->never();

    SyncMinecraftAccount::run($account);
});

// ─── Ineligible: user is in brig ─────────────────────────────────────────────

test('brigged user receives whitelist remove', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'in_brig' => true,
    ]);
    $account = MinecraftAccount::factory()->for($user)->active()->create(['username' => 'Trouble']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist remove Trouble', 'whitelist', 'Trouble', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Removed', 'error' => null]);

    $result = SyncMinecraftAccount::run($account);

    expect($result['eligible'])->toBeFalse()
        ->and($result['whitelist']['action'])->toBe('remove');
});

// ─── Ineligible: parent has disabled Minecraft access ────────────────────────

test('parent-disabled user receives whitelist remove', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'parent_allows_minecraft' => false,
    ]);
    $account = MinecraftAccount::factory()->for($user)->active()->create(['username' => 'KidPlayer']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist remove KidPlayer', 'whitelist', 'KidPlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Removed', 'error' => null]);

    $result = SyncMinecraftAccount::run($account);

    expect($result['eligible'])->toBeFalse()
        ->and($result['whitelist']['action'])->toBe('remove');
});

// ─── Bedrock account ─────────────────────────────────────────────────────────

test('bedrock account uses fwhitelist add command', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $account = MinecraftAccount::factory()->for($user)->active()->create([
        'username' => 'BedrockPlayer',
        'uuid' => 'bedrock-uuid-1234',
        'account_type' => MinecraftAccountType::Bedrock,
    ]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with(Mockery::pattern('/^fwhitelist add /'), 'whitelist', 'BedrockPlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Added', 'error' => null]);

    $result = SyncMinecraftAccount::run($account);

    expect($result['eligible'])->toBeTrue();
});
