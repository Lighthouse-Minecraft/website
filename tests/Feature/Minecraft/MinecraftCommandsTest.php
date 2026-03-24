<?php

declare(strict_types=1);

use App\Actions\SyncMinecraftRanks;
use App\Actions\SyncMinecraftStaff;
use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Models\MinecraftAccount;
use App\Models\MinecraftCommandLog;
use App\Models\MinecraftVerification;
use App\Models\User;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Artisan;

test('cleanup command removes expired verifications', function () {
    $expiredVerification = MinecraftVerification::factory()->expired()->create([
        'status' => 'pending',
        'minecraft_username' => 'ExpiredPlayer',
    ]);

    $activeVerification = MinecraftVerification::factory()->pending()->create([
        'expires_at' => now()->addMinutes(15),
    ]);

    Artisan::call('minecraft:cleanup-expired');

    expect($expiredVerification->fresh()->status)->toBe('expired')
        ->and($activeVerification->fresh()->status)->toBe('pending');
});

test('cleanup command sends whitelist remove command via rcon', function () {
    $user = User::factory()->create();
    MinecraftVerification::factory()->for($user)->expired()->create([
        'status' => 'pending',
        'minecraft_username' => 'ExpiredPlayer',
        'minecraft_uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);
    MinecraftAccount::factory()->for($user)->verifying()->create([
        'username' => 'ExpiredPlayer',
        'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    $rconMock = $this->mock(MinecraftRconService::class);
    $rconMock->shouldReceive('executeCommand')
        ->once()
        ->with('whitelist remove ExpiredPlayer', 'whitelist', 'ExpiredPlayer', \Mockery::any(), \Mockery::any())
        ->andReturn(['success' => true, 'response' => 'Removed']);
    $rconMock->shouldReceive('executeCommand')
        ->once()
        ->with('kick "ExpiredPlayer" Your verification has expired. Please re-verify to rejoin.', 'kick', 'ExpiredPlayer', \Mockery::any(), \Mockery::any())
        ->andReturn(['success' => true, 'response' => 'Kicked']);

    Artisan::call('minecraft:cleanup-expired');

    $this->assertDatabaseHas('minecraft_accounts', [
        'username' => 'ExpiredPlayer',
        'status' => \App\Enums\MinecraftAccountStatus::Cancelled->value,
    ]);
});

test('cleanup command runs successfully', function () {
    MinecraftVerification::factory()->count(3)->expired()->pending()->create();

    $exitCode = Artisan::call('minecraft:cleanup-expired');

    expect($exitCode)->toBe(0);
});

test('rcon service logs commands', function () {
    $user = User::factory()->create();
    $service = app(MinecraftRconService::class);

    $result = $service->executeCommand(
        'whitelist add TestPlayer',
        'whitelist_add',
        'TestPlayer',
        $user,
        ['test' => 'data']
    );

    $this->assertDatabaseHas('minecraft_command_logs', [
        'user_id' => $user->id,
        'command' => 'whitelist add TestPlayer',
        'command_type' => 'whitelist_add',
        'target' => 'TestPlayer',
    ]);
});

test('rcon service records execution time', function () {
    $user = User::factory()->create();
    $service = app(MinecraftRconService::class);

    $service->executeCommand('list', 'server_list', null, $user);

    $log = MinecraftCommandLog::latest()->first();
    expect($log->execution_time_ms)->toBeGreaterThan(0);
});

test('rcon service handles connection failure', function () {
    config(['services.minecraft.rcon_host' => 'invalid-host']);

    $user = User::factory()->create();
    $service = app(MinecraftRconService::class);

    $result = $service->executeCommand('list', 'test', null, $user);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->not->toBeEmpty();

    $this->assertDatabaseHas('minecraft_command_logs', [
        'status' => 'failed',
    ]);
});

// lh command hardening tests

/**
 * Return a MinecraftRconService subclass that returns a controlled RCON response
 * without opening a real network connection.
 */
function makeRconServiceWithResponse(string $response): MinecraftRconService
{
    return new class($response) extends MinecraftRconService
    {
        public function __construct(private readonly string $simulatedResponse) {}

        protected function connectAndSend(string $command): array
        {
            return ['connected' => true, 'result' => $this->simulatedResponse];
        }
    };
}

function makeRconServiceConnectionFailed(): MinecraftRconService
{
    return new class extends MinecraftRconService
    {
        protected function connectAndSend(string $command): array
        {
            return ['connected' => false, 'result' => null];
        }
    };
}

test('lh command is recorded as success when response starts with Success:', function () {
    $user = User::factory()->create();
    $service = makeRconServiceWithResponse('Success: Rank updated for TestPlayer');

    $result = $service->executeCommand('lh setmember TestPlayer traveler', 'rank', 'TestPlayer', $user);

    expect($result['success'])->toBeTrue()
        ->and($result['error'])->toBeNull();

    $this->assertDatabaseHas('minecraft_command_logs', [
        'command' => 'lh setmember TestPlayer traveler',
        'status' => 'success',
    ]);
});

test('lh command is recorded as failed when response is blank', function () {
    $user = User::factory()->create();
    $service = makeRconServiceWithResponse('');

    $result = $service->executeCommand('lh setmember TestPlayer traveler', 'rank', 'TestPlayer', $user);

    expect($result['success'])->toBeFalse();

    $this->assertDatabaseHas('minecraft_command_logs', [
        'command' => 'lh setmember TestPlayer traveler',
        'status' => 'failed',
    ]);
});

test('lh command is recorded as failed when response does not start with Success:', function () {
    $user = User::factory()->create();
    $service = makeRconServiceWithResponse('Player not found: TestPlayer');

    $result = $service->executeCommand('lh setmember TestPlayer traveler', 'rank', 'TestPlayer', $user);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('Player not found');

    $this->assertDatabaseHas('minecraft_command_logs', [
        'command' => 'lh setmember TestPlayer traveler',
        'status' => 'failed',
    ]);
});

test('non-lh command is recorded as success for any non-false response', function () {
    $user = User::factory()->create();
    $service = makeRconServiceWithResponse('Added TestPlayer to the whitelist');

    $result = $service->executeCommand('whitelist add TestPlayer', 'whitelist', 'TestPlayer', $user);

    expect($result['success'])->toBeTrue();

    $this->assertDatabaseHas('minecraft_command_logs', [
        'command' => 'whitelist add TestPlayer',
        'status' => 'success',
    ]);
});

test('rcon connection failure is recorded as failed for lh command', function () {
    $user = User::factory()->create();
    $service = makeRconServiceConnectionFailed();

    $result = $service->executeCommand('lh setmember TestPlayer traveler', 'rank', 'TestPlayer', $user);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe('Failed to connect to RCON server');

    $this->assertDatabaseHas('minecraft_command_logs', [
        'command' => 'lh setmember TestPlayer traveler',
        'status' => 'failed',
    ]);
});

// Synchronous command execution tests

test('SyncMinecraftRanks sends rank command synchronously via rcon service', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'TestPlayer']);

    $rconMock = $this->mock(MinecraftRconService::class);
    $rconMock->shouldReceive('executeCommand')
        ->once()
        ->with('lh setmember TestPlayer traveler', 'rank', 'TestPlayer', Mockery::any(), Mockery::any())
        ->andReturn(['success' => true, 'response' => 'Success: rank set', 'error' => null]);

    SyncMinecraftRanks::run($user);
});

test('SyncMinecraftStaff sends setstaff command synchronously via rcon service', function () {
    $user = User::factory()->create();
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'StaffPlayer']);

    $rconMock = $this->mock(MinecraftRconService::class);
    $rconMock->shouldReceive('executeCommand')
        ->once()
        ->with('lh setstaff StaffPlayer engineer', 'staff', 'StaffPlayer', Mockery::any(), Mockery::any())
        ->andReturn(['success' => true, 'response' => 'Success: staff set', 'error' => null]);

    SyncMinecraftStaff::run($user, StaffDepartment::Engineer);
});

test('SyncMinecraftStaff sends removestaff command synchronously via rcon service', function () {
    $user = User::factory()->create();
    MinecraftAccount::factory()->for($user)->active()->create(['username' => 'StaffPlayer']);

    $rconMock = $this->mock(MinecraftRconService::class);
    $rconMock->shouldReceive('executeCommand')
        ->once()
        ->with('lh removestaff StaffPlayer', 'staff', 'StaffPlayer', Mockery::any(), Mockery::any())
        ->andReturn(['success' => true, 'response' => 'Success: staff removed', 'error' => null]);

    SyncMinecraftStaff::run($user, null);
});
