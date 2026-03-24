<?php

declare(strict_types=1);

use App\Actions\SyncMinecraftAccount;
use App\Actions\SyncMinecraftPermissions;
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

// ─── Multi-account repair ─────────────────────────────────────────────────────

test('repair command repairs all active accounts when a user has multiple', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'MultiAcct1']);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'MultiAcct2']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist add MultiAcct1', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Added', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist add MultiAcct2', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Added', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with(Mockery::pattern('/^lh /'), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->times(4) // 2 accounts × (setmember + removestaff)
        ->andReturn(['success' => true, 'response' => 'Success: done', 'error' => null]);

    $this->artisan('minecraft:repair-permissions', ['--pace' => '0'])
        ->assertSuccessful()
        ->expectsOutputToContain('Whitelist adds:    2')
        ->expectsOutputToContain('Rank changes:      2')
        ->expectsOutputToContain('Staff changes:     2')
        ->expectsOutputToContain('Failures:          0');
});

test('repair command removes all whitelist entries when a brigged user has multiple accounts', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'in_brig' => true,
    ]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'BrigMulti1']);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'BrigMulti2']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist remove BrigMulti1', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Removed', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist remove BrigMulti2', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Removed', 'error' => null]);

    $this->artisan('minecraft:repair-permissions', ['--pace' => '0'])
        ->assertSuccessful()
        ->expectsOutputToContain('Whitelist removes: 2')
        ->expectsOutputToContain('Failures:          0');
});

test('repair command sets staff on all accounts when a staff member has multiple accounts', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'staff_department' => StaffDepartment::Engineer,
    ]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'StaffAcct1']);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'StaffAcct2']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh setstaff StaffAcct1 engineer', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: staff set', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh setstaff StaffAcct2 engineer', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: staff set', 'error' => null]);

    $this->artisan('minecraft:repair-permissions', ['--pace' => '0'])
        ->assertSuccessful()
        ->expectsOutputToContain('Staff changes:     2')
        ->expectsOutputToContain('Failures:          0');
});

test('repair command handles a user with both java and bedrock accounts', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($user)->active()->create([
        'username' => 'JavaUser',
        'account_type' => MinecraftAccountType::Java,
    ]);
    MinecraftAccount::factory()->for($user)->active()->create([
        'username' => 'BedrockUser',
        'uuid' => 'bedrock-uuid-abcd',
        'account_type' => MinecraftAccountType::Bedrock,
    ]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist add JavaUser', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Added', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('fwhitelist add bedrock-uuid-abcd', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Added', 'error' => null]);

    $this->artisan('minecraft:repair-permissions', ['--pace' => '0'])
        ->assertSuccessful()
        ->expectsOutputToContain('Whitelist adds:    2')
        ->expectsOutputToContain('Failures:          0');
});

// ─── Consistency: lifecycle sync and repair agree ─────────────────────────────

test('lifecycle sync and repair command send the same whitelist and rank commands for an eligible user', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'ConsistElig']);

    // Each RCON command fires once from SyncMinecraftPermissions, once from the repair command
    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist add ConsistElig', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->twice()
        ->andReturn(['success' => true, 'response' => 'Added', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh setmember ConsistElig traveler', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->twice()
        ->andReturn(['success' => true, 'response' => 'Success: rank set', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh removestaff ConsistElig', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->twice()
        ->andReturn(['success' => true, 'response' => 'Success: staff removed', 'error' => null]);

    SyncMinecraftPermissions::run($user);
    $this->artisan('minecraft:repair-permissions', ['--pace' => '0'])->assertSuccessful();
});

test('lifecycle sync and repair command both remove a brigged user from the whitelist', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'in_brig' => true,
    ]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'BrigConsist']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist remove BrigConsist', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->twice()
        ->andReturn(['success' => true, 'response' => 'Removed', 'error' => null]);

    SyncMinecraftAccount::run($user->minecraftAccounts()->active()->first());
    $this->artisan('minecraft:repair-permissions', ['--pace' => '0'])->assertSuccessful();
});

test('lifecycle sync and repair command both remove a parent-disabled user from the whitelist', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'parent_allows_minecraft' => false,
    ]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'ParentConsist']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('whitelist remove ParentConsist', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->twice()
        ->andReturn(['success' => true, 'response' => 'Removed', 'error' => null]);

    SyncMinecraftAccount::run($user->minecraftAccounts()->active()->first());
    $this->artisan('minecraft:repair-permissions', ['--pace' => '0'])->assertSuccessful();
});

test('lifecycle sync and repair command both set staff position for a staff user', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'staff_department' => StaffDepartment::Engineer,
    ]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'StaffConsist']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh setstaff StaffConsist engineer', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->twice()
        ->andReturn(['success' => true, 'response' => 'Success: staff set', 'error' => null]);

    SyncMinecraftPermissions::run($user);
    $this->artisan('minecraft:repair-permissions', ['--pace' => '0'])->assertSuccessful();
});

// ─── Dry-run multi-account scenarios ─────────────────────────────────────────

test('dry-run correctly reports all planned actions across multiple users and accounts', function () {
    $eligible = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($eligible)->active()->create(['username' => 'DryElig1']);
    MinecraftAccount::factory()->for($eligible)->active()->create(['username' => 'DryElig2']);

    $brigged = User::factory()->create(['membership_level' => MembershipLevel::Traveler, 'in_brig' => true]);
    MinecraftAccount::factory()->for($brigged)->active()->create(['username' => 'DryBrig1']);
    MinecraftAccount::factory()->for($brigged)->active()->create(['username' => 'DryBrig2']);

    $this->rconMock->shouldReceive('executeCommand')->never();

    $this->artisan('minecraft:repair-permissions', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('[dry-run] whitelist add DryElig1')
        ->expectsOutputToContain('[dry-run] whitelist add DryElig2')
        ->expectsOutputToContain('[dry-run] whitelist remove DryBrig1')
        ->expectsOutputToContain('[dry-run] whitelist remove DryBrig2')
        ->expectsOutputToContain('[dry-run] Whitelist adds:    2')
        ->expectsOutputToContain('[dry-run] Whitelist removes: 2');
});
