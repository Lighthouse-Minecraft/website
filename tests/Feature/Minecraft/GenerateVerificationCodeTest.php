<?php

declare(strict_types=1);

use App\Actions\GenerateVerificationCode;
use App\Enums\MinecraftAccountType;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Models\User;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->action = new GenerateVerificationCode;

    // Mock RCON service
    $this->mock(MinecraftRconService::class, function ($mock) {
        $mock->shouldReceive('executeCommand')
            ->andReturn(['success' => true, 'response' => 'Whitelisted']);
    });
});

test('generates verification code for java account', function () {
    Http::fake([
        'api.mojang.com/*' => Http::response([
            'id' => '069a79f444e94726a5befca90e38aaf5',
            'name' => 'Notch',
        ]),
    ]);

    $result = $this->action->handle($this->user, MinecraftAccountType::Java, 'Notch');

    expect($result['success'])->toBeTrue()
        ->and($result['code'])->toHaveLength(6)
        ->and($result['code'])->toMatch('/^[A-Z2-9]{6}$/')
        ->and($result['expires_at'])->toBeInstanceOf(Carbon\Carbon::class)
        ->and($result['error'])->toBeNull();

    $this->assertDatabaseHas('minecraft_verifications', [
        'user_id' => $this->user->id,
        'code' => $result['code'],
        'account_type' => 'java',
        'minecraft_username' => 'Notch',
        'status' => 'pending',
    ]);
});

test('generates verification code for bedrock account - normalizes dot prefix', function () {
    Http::fake([
        'api.geysermc.org/*' => Http::response([
            'xuid' => '2535428197086765',
        ]),
    ]);

    $result = $this->action->handle($this->user, MinecraftAccountType::Bedrock, 'BedrockPlayer');

    expect($result['success'])->toBeTrue()
        ->and($result['code'])->toHaveLength(6)
        ->and($result['expires_at'])->toBeInstanceOf(Carbon\Carbon::class);

    $this->assertDatabaseHas('minecraft_verifications', [
        'user_id' => $this->user->id,
        'account_type' => 'bedrock',
        'minecraft_username' => '.BedrockPlayer',
        'status' => 'pending',
    ]);

    $this->assertDatabaseHas('minecraft_accounts', [
        'user_id' => $this->user->id,
        'username' => '.BedrockPlayer',
    ]);
});

test('bedrock verification does not double-add the dot if user enters it', function () {
    Http::fake([
        'api.geysermc.org/*' => Http::response([
            'xuid' => '2535428197086765',
        ]),
    ]);

    $result = $this->action->handle($this->user, MinecraftAccountType::Bedrock, '.BedrockPlayer');

    expect($result['success'])->toBeTrue();

    $this->assertDatabaseHas('minecraft_verifications', [
        'minecraft_username' => '.BedrockPlayer',
    ]);
});

test('excludes confusing characters from code', function () {
    Http::fake([
        'api.mojang.com/*' => Http::response(['id' => str_repeat('a', 32), 'name' => 'TestPlayer']),
    ]);

    for ($i = 0; $i < 20; $i++) {
        $user = User::factory()->create();
        $result = (new GenerateVerificationCode)->handle($user, MinecraftAccountType::Java, 'TestPlayer');

        // Test that the code doesn't contain confusing characters
        if ($result['success']) {
            expect($result['code'])->toBeString()
                ->and($result['code'])->not->toContain('0')
                ->and($result['code'])->not->toContain('O')
                ->and($result['code'])->not->toContain('1')
                ->and($result['code'])->not->toContain('I')
                ->and($result['code'])->not->toContain('L')
                ->and($result['code'])->not->toContain('5')
                ->and($result['code'])->not->toContain('S');
        }
    }
});

test('rejects when max accounts reached', function () {
    MinecraftAccount::factory()->count(2)->for($this->user)->create();

    $result = $this->action->handle($this->user, MinecraftAccountType::Java, 'TestPlayer');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('maximum')
        ->and($result['code'])->toBeNull();
});

test('rate limits verification attempts', function () {
    Http::fake(['*' => Http::response(['id' => str_repeat('a', 32), 'name' => 'TestPlayer'])]);

    // Create verifications up to the limit
    for ($i = 0; $i < 10; $i++) {
        MinecraftVerification::factory()->for($this->user)->create([
            'created_at' => now()->subMinutes(30),
        ]);
    }

    $result = $this->action->handle($this->user, MinecraftAccountType::Java, 'TestPlayer');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('rate limit');
});

test('validates java username exists via mojang api', function () {
    Http::fake([
        'api.mojang.com/*' => Http::response(null, 404),
    ]);

    $result = $this->action->handle($this->user, MinecraftAccountType::Java, 'NonExistentPlayer');

    expect($result['success'])->toBeFalse();
});

test('validates bedrock username exists via mcprofile api', function () {
    Http::fake([
        'mcprofile.io/*' => Http::response(null, 404),
    ]);

    $result = $this->action->handle($this->user, MinecraftAccountType::Bedrock, '.NonExistent');

    expect($result['success'])->toBeFalse();
});

test('handles rcon failure gracefully', function () {
    Http::fake(['api.mojang.com/*' => Http::response(['id' => str_repeat('a', 32), 'name' => 'TestPlayer'])]);

    $this->mock(MinecraftRconService::class, function ($mock) {
        $mock->shouldReceive('executeCommand')
            ->andReturn(['success' => false, 'error' => 'Connection refused']);
    });

    $result = $this->action->handle($this->user, MinecraftAccountType::Java, 'TestPlayer');

    expect($result['success'])->toBeFalse();
});

test('sets correct expiration time', function () {
    Http::fake(['api.mojang.com/*' => Http::response(['id' => str_repeat('a', 32), 'name' => 'TestPlayer'])]);

    $gracePeriod = config('lighthouse.minecraft_verification_grace_period_minutes');
    $result = $this->action->handle($this->user, MinecraftAccountType::Java, 'TestPlayer');

    $expiresAt = Carbon\Carbon::parse($result['expires_at']);
    $expectedExpiry = now()->addMinutes($gracePeriod);

    expect($expiresAt->diffInSeconds($expectedExpiry))->toBeLessThan(5);
});

test('rejects when user already has this account verified', function () {
    $uuid = '069a79f444e94726a5befca90e38aaf5';

    // Create an existing verified account for this user
    MinecraftAccount::factory()->for($this->user)->create([
        'uuid' => $uuid,
        'username' => 'Notch',
        'account_type' => 'java',
    ]);

    Http::fake([
        'api.mojang.com/*' => Http::response([
            'id' => $uuid,
            'name' => 'Notch',
        ]),
    ]);

    $result = $this->action->handle($this->user, MinecraftAccountType::Java, 'Notch');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('already verified')
        ->and($result['code'])->toBeNull();
});

test('rejects when user already has this account verified with uuid dashes', function () {
    $uuidWithDashes = '069a79f4-44e9-4726-a5be-fca90e38aaf5';
    $uuidWithoutDashes = '069a79f444e94726a5befca90e38aaf5';

    // Create an existing verified account with UUID that has dashes
    MinecraftAccount::factory()->for($this->user)->create([
        'uuid' => $uuidWithDashes,
        'username' => 'Notch',
        'account_type' => 'java',
    ]);

    Http::fake([
        'api.mojang.com/*' => Http::response([
            'id' => $uuidWithoutDashes, // Mojang returns without dashes
            'name' => 'Notch',
        ]),
    ]);

    $result = $this->action->handle($this->user, MinecraftAccountType::Java, 'Notch');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('already verified')
        ->and($result['code'])->toBeNull();
});

test('rejects when user is in the brig', function () {
    $user = User::factory()->create(['in_brig' => true]);

    Http::fake([
        'api.mojang.com/*' => Http::response([
            'id' => '069a79f444e94726a5befca90e38aaf5',
            'name' => 'Notch',
        ]),
    ]);

    $action = new GenerateVerificationCode;
    $result = $action->handle($user, MinecraftAccountType::Java, 'Notch');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('in the brig')
        ->and($result['code'])->toBeNull();

    // Verify no verification was created
    expect(MinecraftVerification::where('user_id', $user->id)->exists())->toBeFalse();
});
