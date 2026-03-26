<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Models\ActivityLog;
use App\Models\DiscordAccount;
use App\Models\User;
use Livewire\Volt\Volt;

// ─── Waiting step visibility ──────────────────────────────────────────────────

test('Stowaway who linked Discord sees the waiting step', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
    ]);
    DiscordAccount::factory()->active()->for($user)->create();
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSet('step', 'waiting')
        ->assertSee("You're on the Waitlist!", false);
});

test('Stowaway who skipped Discord sees the waiting step', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
    ]);
    ActivityLog::create([
        'causer_id' => $user->id,
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'action' => 'onboarding_discord_skipped',
        'description' => 'Skipped Discord step.',
        'meta' => [],
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSet('step', 'waiting')
        ->assertSee("You're on the Waitlist!", false);
});

test('Stowaway who continued past disabled Discord sees the waiting step', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
        'parent_allows_discord' => false,
    ]);
    ActivityLog::create([
        'causer_id' => $user->id,
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'action' => 'onboarding_discord_disabled',
        'description' => 'Continued past disabled Discord step.',
        'meta' => [],
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSet('step', 'waiting')
        ->assertSee("You're on the Waitlist!", false);
});

// ─── Waiting card content ─────────────────────────────────────────────────────

test('waiting card explains the approval process', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
    ]);
    DiscordAccount::factory()->active()->for($user)->create();
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSee('staff member will review')
        ->assertSee('Traveler');
});

test('waiting card has no action button besides Dismiss', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
    ]);
    DiscordAccount::factory()->active()->for($user)->create();
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSee('Dismiss')
        ->assertDontSee('Connect Discord')
        ->assertDontSee('Skip for now')
        ->assertDontSee('Continue');
});

// ─── Traveler does not see the waiting step ───────────────────────────────────

test('Traveler with no Minecraft account does not see the waiting step', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSet('step', 'minecraft')
        ->assertDontSee("You're on the Waitlist!", false);
});

test('Traveler does not see the waiting step even after having linked Discord', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
    ]);
    DiscordAccount::factory()->active()->for($user)->create();
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertDontSee("You're on the Waitlist!", false);
});

// ─── Dismiss still available on waiting step ─────────────────────────────────

test('Dismiss from the waiting step sets dismissed_at', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
    ]);
    DiscordAccount::factory()->active()->for($user)->create();
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSet('step', 'waiting')
        ->call('dismiss');

    expect($user->fresh()->onboarding_wizard_dismissed_at)->not->toBeNull();
});
