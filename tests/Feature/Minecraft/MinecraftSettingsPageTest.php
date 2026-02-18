<?php

declare(strict_types=1);

use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Models\User;
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
    Volt::test('settings.minecraft-accounts')
        ->assertSee('Add Minecraft Account')
        ->assertSee('Minecraft Username');
});

test('generates verification code', function () {
    Http::fake(['api.mojang.com/*' => Http::response(['id' => str_repeat('a', 32), 'name' => 'TestPlayer'])]);

    Volt::test('settings.minecraft-accounts')
        ->set('username', 'TestPlayer')
        ->set('accountType', 'java')
        ->call('generateCode')
        ->assertHasNoErrors()
        ->assertSee('Your Verification Code');
});

test('displays active verification code', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code' => 'ABC123',
    ]);

    Volt::test('settings.minecraft-accounts')
        ->assertSee('ABC123')
        ->assertSee('Waiting for verification');
});

test('validates username required', function () {
    Volt::test('settings.minecraft-accounts')
        ->set('username', '')
        ->set('accountType', 'java')
        ->call('generateCode')
        ->assertHasErrors(['username' => 'required']);
});

test('validates account type required', function () {
    Volt::test('settings.minecraft-accounts')
        ->set('username', 'TestPlayer')
        ->set('accountType', null)
        ->call('generateCode')
        ->assertHasErrors(['accountType' => 'required']);
});

test('removes linked account', function () {
    $account = MinecraftAccount::factory()->for($this->user)->create();

    Volt::test('settings.minecraft-accounts')
        ->call('remove', $account->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('minecraft_accounts', [
        'id' => $account->id,
    ]);
});

test('cannot remove another users account', function () {
    $otherUser = User::factory()->create();
    $account = MinecraftAccount::factory()->for($otherUser)->create();

    Volt::test('settings.minecraft-accounts')
        ->call('remove', $account->id)
        ->assertForbidden();

    $this->assertDatabaseHas('minecraft_accounts', [
        'id' => $account->id,
    ]);
});

test('shows remaining account slots', function () {
    MinecraftAccount::factory()->for($this->user)->create();

    Volt::test('settings.minecraft-accounts')
        ->assertSee('1 slot remaining');
});

test('shows max accounts reached', function () {
    MinecraftAccount::factory()->count(2)->for($this->user)->create();

    Volt::test('settings.minecraft-accounts')
        ->assertSee('maximum');
});

test('polls for verification completion', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create();

    $component = Volt::test('settings.minecraft-accounts');

    // Simulate completion
    $verification->update(['status' => 'completed']);

    $component->call('checkVerification')
        ->assertSet('activeVerification', null);
});
