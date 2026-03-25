<?php

declare(strict_types=1);

use App\Actions\SyncMinecraftAccount;
use App\Enums\MembershipLevel;
use App\Enums\MinecraftAccountType;
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

// ─── Eligible user (Traveler+, not in brig, parent allows) ───────────────────

test('eligible user receives single lh syncuser command', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $account = MinecraftAccount::factory()->for($user)->active()->create(['username' => 'Steve']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser Steve traveler none', 'sync', 'Steve', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced Steve', 'error' => null]);

    $result = SyncMinecraftAccount::run($account);

    expect($result['eligible'])->toBeTrue()
        ->and($result['whitelist']['action'])->toBe('add')
        ->and($result['whitelist']['success'])->toBeTrue()
        ->and($result['rank']['rank'])->toBe('traveler')
        ->and($result['staff']['action'])->toBe('remove');
});

test('eligible staff Officer receives lh syncuser with department name (no _crew suffix)', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Engineer,
    ]);
    $account = MinecraftAccount::factory()->for($user)->active()->create(['username' => 'OfficerPlayer']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser OfficerPlayer traveler engineer', 'sync', 'OfficerPlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced OfficerPlayer', 'error' => null]);

    $result = SyncMinecraftAccount::run($account);

    expect($result['eligible'])->toBeTrue()
        ->and($result['staff']['action'])->toBe('set')
        ->and($result['staff']['department'])->toBe('engineer');
});

test('eligible staff Crew Member receives lh syncuser with _crew suffix', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'staff_rank' => StaffRank::CrewMember,
        'staff_department' => StaffDepartment::Engineer,
    ]);
    $account = MinecraftAccount::factory()->for($user)->active()->create(['username' => 'CrewPlayer']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser CrewPlayer traveler engineer_crew', 'sync', 'CrewPlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced CrewPlayer', 'error' => null]);

    $result = SyncMinecraftAccount::run($account);

    expect($result['eligible'])->toBeTrue()
        ->and($result['staff']['action'])->toBe('set')
        ->and($result['staff']['department'])->toBe('engineer_crew');
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

test('ineligible user does not receive lh syncuser command', function () {
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

test('bedrock account uses lh syncuser with -bedrock suffix', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $account = MinecraftAccount::factory()->for($user)->active()->create([
        'username' => 'BedrockPlayer',
        'uuid' => 'bedrock-uuid-1234',
        'account_type' => MinecraftAccountType::Bedrock,
    ]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with(Mockery::pattern('/^lh syncuser BedrockPlayer traveler none -bedrock /'), 'sync', 'BedrockPlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced BedrockPlayer', 'error' => null]);

    $result = SyncMinecraftAccount::run($account);

    expect($result['eligible'])->toBeTrue();
});
