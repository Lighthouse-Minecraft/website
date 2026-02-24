<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Models\DiscordAccount;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('discord', 'pages');

it('can render the discord settings page', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);

    $this->actingAs($user)
        ->get(route('settings.discord-account'))
        ->assertOk();
});

it('requires authentication', function () {
    $this->get(route('settings.discord-account'))
        ->assertRedirect(route('login'));
});

it('shows link button for eligible users', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);

    $this->actingAs($user)
        ->get(route('settings.discord-account'))
        ->assertSee('Link Discord Account');
});

it('shows upgrade message for ineligible users', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);

    $this->actingAs($user)
        ->get(route('settings.discord-account'))
        ->assertSee('promoted to Traveler');
});

it('shows linked accounts', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $account = DiscordAccount::factory()->create([
        'user_id' => $user->id,
        'username' => 'linked_discord_user',
    ]);

    $this->actingAs($user)
        ->get(route('settings.discord-account'))
        ->assertSee('linked_discord_user');
});

it('can unlink an account', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $account = DiscordAccount::factory()->active()->create(['user_id' => $user->id]);

    Volt::actingAs($user)
        ->test('settings.discord-account')
        ->call('confirmUnlink', $account->id)
        ->call('unlinkAccount');

    expect(DiscordAccount::find($account->id))->toBeNull();
});
