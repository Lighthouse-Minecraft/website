<?php

declare(strict_types=1);

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

// ─── No accounts ─────────────────────────────────────────────────────────────

test('exits successfully with message when no active accounts exist', function () {
    $this->artisan('minecraft:repair-permissions', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('No active Minecraft accounts found');
});

// ─── Dry-run behavior ────────────────────────────────────────────────────────

test('dry-run reports planned whitelist add and rank for eligible account', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'EligiblePlayer']);

    $this->rconMock->shouldReceive('executeCommand')->never();

    $this->artisan('minecraft:repair-permissions', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('[dry-run] whitelist add EligiblePlayer')
        ->expectsOutputToContain('[dry-run] lh setmember EligiblePlayer traveler')
        ->expectsOutputToContain('[dry-run] lh removestaff EligiblePlayer');
});

test('dry-run reports planned whitelist remove for brigged account', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'in_brig' => true,
    ]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'BriggedPlayer']);

    $this->rconMock->shouldReceive('executeCommand')->never();

    $this->artisan('minecraft:repair-permissions', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('[dry-run] whitelist remove BriggedPlayer')
        ->expectsOutputToContain('in brig');
});

test('dry-run reports planned whitelist remove for below-threshold account', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'NewbiePlayer']);

    $this->artisan('minecraft:repair-permissions', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('[dry-run] whitelist remove NewbiePlayer')
        ->expectsOutputToContain('below server access threshold');
});

test('dry-run reports planned whitelist remove for parent-disabled account', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'parent_allows_minecraft' => false,
    ]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'KidPlayer']);

    $this->artisan('minecraft:repair-permissions', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('[dry-run] whitelist remove KidPlayer')
        ->expectsOutputToContain('parent disabled');
});

test('dry-run reports setstaff command for staff members', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'staff_department' => StaffDepartment::Engineer,
    ]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'StaffMember']);

    $this->artisan('minecraft:repair-permissions', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('[dry-run] lh setstaff StaffMember engineer');
});

test('dry-run sends no RCON commands and prints summary', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'APlayer']);

    $this->rconMock->shouldReceive('executeCommand')->never();

    $this->artisan('minecraft:repair-permissions', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Summary')
        ->expectsOutputToContain('[dry-run] Whitelist adds:    1')
        ->expectsOutputToContain('[dry-run] Rank changes:      1');
});

// ─── Live execution ───────────────────────────────────────────────────────────

test('live mode sends whitelist, rank, and staff RCON commands for eligible account', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'LivePlayer']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist add LivePlayer', 'whitelist', 'LivePlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Added', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh setmember LivePlayer traveler', 'rank', 'LivePlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: rank set', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh removestaff LivePlayer', 'staff', 'LivePlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: staff removed', 'error' => null]);

    $this->artisan('minecraft:repair-permissions', ['--pace' => '0'])
        ->assertSuccessful()
        ->expectsOutputToContain('Whitelist adds:    1')
        ->expectsOutputToContain('Rank changes:      1')
        ->expectsOutputToContain('Staff changes:     1')
        ->expectsOutputToContain('Failures:          0');
});

test('live mode sends whitelist remove for ineligible account', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'in_brig' => true,
    ]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'BrigPlayer']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist remove BrigPlayer', 'whitelist', 'BrigPlayer', Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Removed', 'error' => null]);

    $this->artisan('minecraft:repair-permissions', ['--pace' => '0'])
        ->assertSuccessful()
        ->expectsOutputToContain('Whitelist removes: 1');
});

test('live mode records failures in summary when RCON returns error', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'FailPlayer']);

    $this->rconMock->shouldReceive('executeCommand')
        ->andReturn(['success' => false, 'response' => null, 'error' => 'Server offline']);

    $this->artisan('minecraft:repair-permissions', ['--pace' => '0'])
        ->assertSuccessful()
        ->expectsOutputToContain('Failures:');
});

// ─── Mixed account scenarios ─────────────────────────────────────────────────

test('mixed eligible and ineligible accounts processed correctly in dry-run', function () {
    $eligible = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($eligible)->active()->create(['username' => 'EligPlayer']);

    $brigged = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'in_brig' => true,
    ]);
    MinecraftAccount::factory()->for($brigged)->active()->create(['username' => 'BrigPlayer2']);

    $belowThreshold = User::factory()->create(['membership_level' => MembershipLevel::Drifter]);
    MinecraftAccount::factory()->for($belowThreshold)->active()->create(['username' => 'DrifterPlayer']);

    $this->rconMock->shouldReceive('executeCommand')->never();

    $this->artisan('minecraft:repair-permissions', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('[dry-run] whitelist add EligPlayer')
        ->expectsOutputToContain('[dry-run] whitelist remove BrigPlayer2')
        ->expectsOutputToContain('[dry-run] whitelist remove DrifterPlayer')
        ->expectsOutputToContain('[dry-run] Whitelist adds:    1')
        ->expectsOutputToContain('[dry-run] Whitelist removes: 2');
});

test('mixed live run sends correct RCON commands with pace=0', function () {
    $eligible = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($eligible)->active()->create(['username' => 'EligPlayer2']);

    $ineligible = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    MinecraftAccount::factory()->for($ineligible)->active()->create(['username' => 'IneligPlayer']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist add EligPlayer2', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Added', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with(Mockery::pattern('/^lh /'), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->twice()
        ->andReturn(['success' => true, 'response' => 'Success: done', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist remove IneligPlayer', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Removed', 'error' => null]);

    $this->artisan('minecraft:repair-permissions', ['--pace' => '0'])
        ->assertSuccessful()
        ->expectsOutputToContain('Whitelist adds:    1')
        ->expectsOutputToContain('Whitelist removes: 1')
        ->expectsOutputToContain('Failures:          0');
});

// ─── Bedrock account ─────────────────────────────────────────────────────────

test('bedrock account uses fwhitelist command in dry-run', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($user)->active()->create([
        'username' => 'BedrockGuy',
        'uuid' => 'bedrock-uuid-5678',
        'account_type' => MinecraftAccountType::Bedrock,
    ]);

    $this->artisan('minecraft:repair-permissions', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('[dry-run] fwhitelist add');
});
