<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;

uses()->group('discord', 'onboarding');

function stowawayWithDiscord(): User
{
    return User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'parent_allows_discord' => true,
        'in_brig' => false,
    ]);
}

it('stores onboarding origin in session when from=onboarding param is passed', function () {
    $user = stowawayWithDiscord();

    $this->actingAs($user)
        ->get(route('auth.discord.redirect', ['from' => 'onboarding']));

    expect(session('discord_oauth_from'))->toBe('onboarding');
});

it('does not store onboarding origin when from param is absent', function () {
    $user = stowawayWithDiscord();

    $this->actingAs($user)
        ->get(route('auth.discord.redirect'));

    expect(session('discord_oauth_from'))->toBeNull();
});

it('does not store origin for unrecognised from values', function () {
    $user = stowawayWithDiscord();

    $this->actingAs($user)
        ->get(route('auth.discord.redirect', ['from' => 'evil']));

    expect(session('discord_oauth_from'))->toBeNull();
});

it('clears stale onboarding session when redirect is not from onboarding', function () {
    $user = stowawayWithDiscord();

    $this->actingAs($user)
        ->withSession(['discord_oauth_from' => 'onboarding'])
        ->get(route('auth.discord.redirect'));

    expect(session('discord_oauth_from'))->toBeNull();
});

it('callback redirects to dashboard on success when from=onboarding was stored', function () {
    $user = stowawayWithDiscord();

    $mockSocialUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $mockSocialUser->shouldReceive('getId')->andReturn('999888777');
    $mockSocialUser->shouldReceive('getNickname')->andReturn('TestUser');
    $mockSocialUser->shouldReceive('getName')->andReturn('TestUser');
    $mockSocialUser->token = 'access-token';
    $mockSocialUser->refreshToken = 'refresh-token';
    $mockSocialUser->expiresIn = 604800;
    $mockSocialUser->user = ['global_name' => 'Test User', 'avatar' => null];

    $mockProvider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
    $mockProvider->shouldReceive('scopes')->andReturnSelf();
    $mockProvider->shouldReceive('redirect')->andReturn(redirect('https://discord.com'));
    $mockProvider->shouldReceive('user')->andReturn($mockSocialUser);

    Socialite::shouldReceive('driver')->with('discord')->andReturn($mockProvider);

    $this->actingAs($user)
        ->withSession(['discord_oauth_from' => 'onboarding'])
        ->get(route('auth.discord.callback'))
        ->assertRedirect(route('dashboard'));
});

it('callback redirects to settings on success without onboarding session', function () {
    $user = stowawayWithDiscord();

    $mockSocialUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $mockSocialUser->shouldReceive('getId')->andReturn('111222333');
    $mockSocialUser->shouldReceive('getNickname')->andReturn('AnotherUser');
    $mockSocialUser->shouldReceive('getName')->andReturn('AnotherUser');
    $mockSocialUser->token = 'access-token';
    $mockSocialUser->refreshToken = 'refresh-token';
    $mockSocialUser->expiresIn = 604800;
    $mockSocialUser->user = ['global_name' => 'Another User', 'avatar' => null];

    $mockProvider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
    $mockProvider->shouldReceive('scopes')->andReturnSelf();
    $mockProvider->shouldReceive('redirect')->andReturn(redirect('https://discord.com'));
    $mockProvider->shouldReceive('user')->andReturn($mockSocialUser);

    Socialite::shouldReceive('driver')->with('discord')->andReturn($mockProvider);

    $this->actingAs($user)
        ->get(route('auth.discord.callback'))
        ->assertRedirect(route('settings.discord-account'));
});

it('callback redirects to dashboard on OAuth error when from=onboarding', function () {
    $user = stowawayWithDiscord();

    $mockProvider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
    $mockProvider->shouldReceive('scopes')->andReturnSelf();
    $mockProvider->shouldReceive('redirect')->andReturn(redirect('https://discord.com'));
    $mockProvider->shouldReceive('user')->andThrow(new \Exception('OAuth failed'));

    Socialite::shouldReceive('driver')->with('discord')->andReturn($mockProvider);

    $this->actingAs($user)
        ->withSession(['discord_oauth_from' => 'onboarding'])
        ->get(route('auth.discord.callback'))
        ->assertRedirect(route('dashboard'));
});

it('wizard discord step links directly to OAuth redirect with onboarding param', function () {
    $user = stowawayWithDiscord();
    $this->actingAs($user);

    \Livewire\Volt\Volt::test('onboarding.wizard')
        ->assertSee(route('auth.discord.redirect', ['from' => 'onboarding']));
});

it('wizard discord step does not link to settings page', function () {
    $user = stowawayWithDiscord();
    $this->actingAs($user);

    \Livewire\Volt\Volt::test('onboarding.wizard')
        ->assertDontSee(route('settings.discord-account'));
});
