<?php

declare(strict_types=1);

use App\Actions\SyncMinecraftAccount;
use App\Actions\SyncMinecraftPermissions;
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

// ─── Multi-account repair ─────────────────────────────────────────────────────

test('repair command repairs all active accounts when a user has multiple', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'MultiAcct1']);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'MultiAcct2']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncstart', 'sync', null, null, Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Backed up and cleared', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser MultiAcct1 traveler none', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced MultiAcct1', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser MultiAcct2 traveler none', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced MultiAcct2', 'error' => null]);

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

test('repair command syncs staff on all accounts when a staff member has multiple accounts', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'staff_rank' => StaffRank::CrewMember,
        'staff_department' => StaffDepartment::Engineer,
    ]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'StaffAcct1']);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'StaffAcct2']);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncstart', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Backed up and cleared', 'error' => null]);

    // No staff_rank set → crew behavior → engineer_crew
    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser StaffAcct1 traveler engineer_crew', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced StaffAcct1', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser StaffAcct2 traveler engineer_crew', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced StaffAcct2', 'error' => null]);

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
        ->with('lh syncstart', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Backed up and cleared', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser JavaUser traveler none', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced JavaUser', 'error' => null]);

    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser BedrockUser traveler none -bedrock bedrock-uuid-abcd', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'response' => 'Success: Synced BedrockUser', 'error' => null]);

    $this->artisan('minecraft:repair-permissions', ['--pace' => '0'])
        ->assertSuccessful()
        ->expectsOutputToContain('Whitelist adds:    2')
        ->expectsOutputToContain('Failures:          0');
});

// ─── Consistency: lifecycle sync and repair agree ─────────────────────────────

test('lifecycle sync and repair command both handle an eligible user via lh syncuser', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'ConsistElig']);

    // Both paths now use lh syncuser — called twice total
    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser ConsistElig traveler none', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->twice()
        ->andReturn(['success' => true, 'response' => 'Success: Synced ConsistElig', 'error' => null]);

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

test('lifecycle sync and repair command both handle a staff user via lh syncuser', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'staff_rank' => StaffRank::CrewMember,
        'staff_department' => StaffDepartment::Engineer,
    ]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'StaffConsist']);

    // Both paths use lh syncuser with _crew suffix (no staff_rank → crew)
    $this->rconMock->shouldReceive('executeCommand')
        ->with('lh syncuser StaffConsist traveler engineer_crew', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->twice()
        ->andReturn(['success' => true, 'response' => 'Success: Synced StaffConsist', 'error' => null]);

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
        ->expectsOutputToContain('[dry-run] lh syncuser DryElig1 traveler none')
        ->expectsOutputToContain('[dry-run] lh syncuser DryElig2 traveler none')
        ->expectsOutputToContain('[dry-run] whitelist remove DryBrig1')
        ->expectsOutputToContain('[dry-run] whitelist remove DryBrig2')
        ->expectsOutputToContain('[dry-run] Whitelist adds:    2')
        ->expectsOutputToContain('[dry-run] Whitelist removes: 2');
});
