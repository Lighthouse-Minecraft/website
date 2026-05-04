<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Models\ActivityLog;
use App\Models\DiscordAccount;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

// ─── Dashboard takeover ───────────────────────────────────────────────────────

test('Stowaway with no Discord and no dismissed_at sees the wizard instead of normal dashboard', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
        'onboarding_wizard_dismissed_at' => null,
        'onboarding_wizard_completed_at' => null,
    ]);
    loginAs($user);

    get('dashboard')
        ->assertDontSee('Donations')
        ->assertSee('Connect Your Discord Account');
});

test('user with dismissed_at set sees the normal dashboard', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
        'onboarding_wizard_dismissed_at' => now(),
    ]);
    loginAs($user);

    get('dashboard')->assertSee('Donations');
});

// ─── Discord step ─────────────────────────────────────────────────────────────

test('wizard mounts on discord step for a Stowaway with no Discord account', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSet('step', 'discord');
});

test('Discord step shows Connect Discord button and Skip for now option', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSee('Connect Discord')
        ->assertSee('Skip for now')
        ->assertSee(route('auth.discord.redirect', ['from' => 'onboarding']));
});

test('Connect Discord button points directly to the OAuth redirect with onboarding param', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSee(route('auth.discord.redirect', ['from' => 'onboarding']))
        ->assertDontSee(route('settings.discord-account'));
});

test('Skip for now records onboarding_discord_skipped in activity log', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->call('skipDiscord');

    expect(
        ActivityLog::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('action', 'onboarding_discord_skipped')
            ->exists()
    )->toBeTrue();
});

test('Skipping Discord advances step to waiting', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->call('skipDiscord')
        ->assertSet('step', 'waiting');
});

// ─── Parent-disabled Discord state ───────────────────────────────────────────

test('parent-disabled Discord state shows explanation and Continue button', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
        'parent_allows_discord' => false,
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSee('Discord is currently disabled')
        ->assertSee('Continue')
        ->assertDontSee('Skip for now');
});

test('Continue on parent-disabled Discord records onboarding_discord_disabled in activity log', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
        'parent_allows_discord' => false,
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->call('continueDisabledDiscord');

    expect(
        ActivityLog::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('action', 'onboarding_discord_disabled')
            ->exists()
    )->toBeTrue();
});

test('Continue on parent-disabled Discord advances step to waiting', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
        'parent_allows_discord' => false,
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->call('continueDisabledDiscord')
        ->assertSet('step', 'waiting');
});

// ─── Dismiss action ───────────────────────────────────────────────────────────

test('Dismiss button is present on the Discord step', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSee('Dismiss');
});

test('Dismissing sets onboarding_wizard_dismissed_at on the user', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->call('dismiss');

    expect($user->fresh()->onboarding_wizard_dismissed_at)->not->toBeNull();
});

test('Dismissing records onboarding_wizard_dismissed in activity log', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->call('dismiss');

    expect(
        ActivityLog::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('action', 'onboarding_wizard_dismissed')
            ->exists()
    )->toBeTrue();
});

test('after dismissal, the normal dashboard widgets are shown', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
        'onboarding_wizard_dismissed_at' => now(),
    ]);
    loginAs($user);

    get('dashboard')->assertSee('Donations');
});

// ─── Dismiss button on waiting step ──────────────────────────────────────────

test('Dismiss button is present on the waiting step', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
    ]);
    DiscordAccount::factory()->active()->for($user)->create();
    ActivityLog::create([
        'causer_id' => $user->id,
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'action' => 'onboarding_discord_join_done',
        'description' => 'Confirmed joining Discord server.',
        'meta' => [],
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSet('step', 'waiting')
        ->assertSee('Dismiss');
});
