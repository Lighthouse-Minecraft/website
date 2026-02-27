<?php

declare(strict_types=1);

use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Models\User;
use App\Services\MinecraftRconService;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->mock(MinecraftRconService::class, function ($mock) {
        $mock->shouldReceive('executeCommand')
            ->andReturn(['success' => true, 'response' => 'Removed from whitelist']);
    });
});

test('removes a verifying account when user has no active verification code state', function () {
    $account = MinecraftAccount::factory()->for($this->user)->verifying()->create();

    $this->actingAs($this->user);

    Volt::test('settings.minecraft-accounts')
        ->call('confirmRemoveVerifying', $account->id)
        ->call('removeVerifyingAccount')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('minecraft_accounts', ['id' => $account->id]);
});

test('expires the associated verification record when removing a verifying account', function () {
    $account = MinecraftAccount::factory()->for($this->user)->verifying()->create();
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'minecraft_uuid' => $account->uuid,
    ]);

    $this->actingAs($this->user);

    Volt::test('settings.minecraft-accounts')
        ->call('confirmRemoveVerifying', $account->id)
        ->call('removeVerifyingAccount');

    expect($verification->fresh()->status)->toBe('expired');
});

test('marks account cancelled if RCON server is offline', function () {
    $account = MinecraftAccount::factory()->for($this->user)->verifying()->create();

    $this->mock(MinecraftRconService::class, function ($mock) {
        $mock->shouldReceive('executeCommand')
            ->andReturn(['success' => false, 'response' => 'Server unreachable']);
    });

    $this->actingAs($this->user);

    Volt::test('settings.minecraft-accounts')
        ->call('confirmRemoveVerifying', $account->id)
        ->call('removeVerifyingAccount');

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Cancelled);
});

test('cannot remove another users verifying account', function () {
    $otherUser = User::factory()->create();
    $account = MinecraftAccount::factory()->for($otherUser)->verifying()->create();

    $this->actingAs($this->user);

    Volt::test('settings.minecraft-accounts')
        ->call('confirmRemoveVerifying', $account->id)
        ->call('removeVerifyingAccount')
        ->assertDispatched('toast-show');

    // Account should still exist since it wasn't found for this user
    $this->assertDatabaseHas('minecraft_accounts', ['id' => $account->id]);
});

test('cannot remove a non-verifying account via removeVerifyingAccount', function () {
    $account = MinecraftAccount::factory()->for($this->user)->active()->create();

    $this->actingAs($this->user);

    Volt::test('settings.minecraft-accounts')
        ->call('confirmRemoveVerifying', $account->id)
        ->call('removeVerifyingAccount')
        ->assertDispatched('toast-show');

    // Active account should still exist
    $this->assertDatabaseHas('minecraft_accounts', [
        'id' => $account->id,
        'status' => MinecraftAccountStatus::Active,
    ]);
});
