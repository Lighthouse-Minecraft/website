<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Models\User;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('displays minecraft settings page', function () {
    $response = $this->get('/settings/minecraft-accounts');

    $response->assertSuccessful();
});

test('requires authentication', function () {
    auth()->logout();

    $response = $this->get('/settings/minecraft-accounts');

    $response->assertRedirect('/login');
});

test('displays linked accounts', function () {
    MinecraftAccount::factory()->count(2)->for($this->user)->create();

    Volt::test('settings.minecraft-accounts')
        ->assertSee('Minecraft Accounts')
        ->assertSee('Linked Accounts');
});

test('shows verification form when no active verification', function () {
    $this->get('/settings/minecraft-accounts')
        ->assertSuccessful()
        ->assertSee('Link New Account');
});

test('shows promotion callout instead of form for users below traveler rank', function () {
    $stowaway = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();
    $this->actingAs($stowaway);

    $this->get('/settings/minecraft-accounts')
        ->assertSuccessful()
        ->assertSee('promoted you to Traveler rank')
        ->assertDontSee('Link New Account');
});

test('generates verification code', function () {
    Http::fake(['api.mojang.com/*' => Http::response(['id' => str_repeat('a', 32), 'name' => 'TestPlayer'])]);

    $this->mock(MinecraftRconService::class, function ($mock) {
        $mock->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => 'OK']);
    });

    Volt::test('settings.minecraft-accounts')
        ->set('username', 'TestPlayer')
        ->set('accountType', 'java')
        ->call('generateCode')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('minecraft_verifications', [
        'user_id' => $this->user->id,
        'minecraft_username' => 'TestPlayer',
        'account_type' => 'java',
    ]);
});

test('displays active verification code', function () {
    MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
    ]);

    $this->get('/settings/minecraft-accounts')
        ->assertSuccessful()
        ->assertSee('ABC123');
});

test('validates username required', function () {
    Volt::test('settings.minecraft-accounts')
        ->set('username', '')
        ->set('accountType', 'java')
        ->call('generateCode')
        ->assertHasErrors(['username' => 'required']);
});

test('removes linked account', function () {
    $account = MinecraftAccount::factory()->for($this->user)->create();

    $this->mock(MinecraftRconService::class, function ($mock) {
        $mock->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => 'OK']);
    });

    Volt::test('settings.minecraft-accounts')
        ->set('accountToUnlink', $account->id)
        ->call('unlinkAccount')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('minecraft_accounts', [
        'id' => $account->id,
    ]);
});

test('cannot remove another users account', function () {
    $otherUser = User::factory()->create();
    $account = MinecraftAccount::factory()->for($otherUser)->create();

    Volt::test('settings.minecraft-accounts')
        ->set('accountToUnlink', $account->id)
        ->call('unlinkAccount')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('minecraft_accounts', [
        'id' => $account->id,
    ]);
});

test('shows remaining account slots', function () {
    MinecraftAccount::factory()->for($this->user)->create();

    $this->get('/settings/minecraft-accounts')
        ->assertSuccessful()
        ->assertSee('more account');
});

test('shows max accounts reached', function () {
    MinecraftAccount::factory()->count(config('lighthouse.max_minecraft_accounts'))->for($this->user)->create();

    Volt::test('settings.minecraft-accounts')
        ->assertSee('maximum');
});

test('polls for verification completion', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create();

    $component = Volt::test('settings.minecraft-accounts');

    // Simulate completion
    $verification->update(['status' => 'completed']);

    $component->call('checkVerification')
        ->assertSet('verificationCode', null);
});
