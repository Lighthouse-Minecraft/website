<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Models\User;

uses()->group('discord', 'oauth');

it('requires authentication for discord redirect', function () {
    $this->get(route('auth.discord.redirect'))
        ->assertRedirect(route('login'));
});

it('requires traveler rank to access discord redirect', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);

    $this->actingAs($user)
        ->get(route('auth.discord.redirect'))
        ->assertForbidden();
});

it('blocks brigged users from discord redirect', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'in_brig' => true,
    ]);

    $this->actingAs($user)
        ->get(route('auth.discord.redirect'))
        ->assertForbidden();
});

it('allows eligible users to access discord redirect', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);

    $response = $this->actingAs($user)
        ->get(route('auth.discord.redirect'));

    // Should redirect to Discord OAuth
    expect($response->status())->toBe(302);
    expect($response->headers->get('Location'))->toContain('discord.com');
});
