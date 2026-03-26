<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Models\ActivityLog;
use App\Models\DiscordAccount;
use App\Models\MinecraftAccount;
use App\Models\User;

// ─── shouldShowOnboardingWizard() ────────────────────────────────────────────

test('shouldShowOnboardingWizard returns true for a Stowaway with no accounts and no dismissed/completed', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);

    expect($user->shouldShowOnboardingWizard())->toBeTrue();
});

test('shouldShowOnboardingWizard returns true for a Traveler with no dismissed/completed', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);

    expect($user->shouldShowOnboardingWizard())->toBeTrue();
});

test('shouldShowOnboardingWizard returns false for a Drifter', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Drifter]);

    expect($user->shouldShowOnboardingWizard())->toBeFalse();
});

test('shouldShowOnboardingWizard returns false for a Resident (already past onboarding)', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Resident]);

    expect($user->shouldShowOnboardingWizard())->toBeFalse();
});

test('shouldShowOnboardingWizard returns false when dismissed_at is set', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'onboarding_wizard_dismissed_at' => now(),
    ]);

    expect($user->shouldShowOnboardingWizard())->toBeFalse();
});

test('shouldShowOnboardingWizard returns false when completed_at is set', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'onboarding_wizard_completed_at' => now(),
    ]);

    expect($user->shouldShowOnboardingWizard())->toBeFalse();
});

test('shouldShowOnboardingWizard returns false for a user whose dismissed_at was set by backfill', function () {
    // Represents an existing user whose dismissed_at was set by the migration backfill
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'onboarding_wizard_dismissed_at' => now()->subMinutes(5),
    ]);

    expect($user->shouldShowOnboardingWizard())->toBeFalse();
});

// ─── currentOnboardingStep() — Stowaway Discord step ─────────────────────────

test('currentOnboardingStep returns discord for a Stowaway with no Discord accounts', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);

    expect($user->currentOnboardingStep())->toBe('discord');
});

test('currentOnboardingStep returns discord for a Stowaway with parent_allows_discord false', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'parent_allows_discord' => false,
    ]);

    expect($user->currentOnboardingStep())->toBe('discord');
});

// ─── currentOnboardingStep() — Stowaway waiting step ─────────────────────────

test('currentOnboardingStep returns waiting for a Stowaway who has linked Discord', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    DiscordAccount::factory()->active()->for($user)->create();

    expect($user->currentOnboardingStep())->toBe('waiting');
});

test('currentOnboardingStep returns waiting for a Stowaway who skipped Discord', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);

    ActivityLog::create([
        'causer_id' => $user->id,
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'action' => 'onboarding_discord_skipped',
        'description' => 'Skipped Discord step.',
        'meta' => [],
    ]);

    expect($user->currentOnboardingStep())->toBe('waiting');
});

test('currentOnboardingStep returns waiting for a Stowaway who continued past disabled Discord', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
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

    expect($user->currentOnboardingStep())->toBe('waiting');
});

// ─── currentOnboardingStep() — Traveler Minecraft step ───────────────────────

test('currentOnboardingStep returns minecraft for a Traveler with no Minecraft accounts', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);

    expect($user->currentOnboardingStep())->toBe('minecraft');
});

test('currentOnboardingStep returns complete for a Traveler who has linked Minecraft', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    MinecraftAccount::factory()->active()->for($user)->create();

    expect($user->currentOnboardingStep())->toBe('complete');
});

test('currentOnboardingStep returns minecraft for a Traveler with parent_allows_minecraft false', function () {
    // Shows the disabled-Minecraft explanation card so the user can click Continue
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'parent_allows_minecraft' => false,
    ]);

    expect($user->currentOnboardingStep())->toBe('minecraft');
});

test('currentOnboardingStep returns complete for a Traveler who skipped Minecraft', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);

    ActivityLog::create([
        'causer_id' => $user->id,
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'action' => 'onboarding_minecraft_skipped',
        'description' => 'Skipped Minecraft step.',
        'meta' => [],
    ]);

    expect($user->currentOnboardingStep())->toBe('complete');
});

test('currentOnboardingStep returns complete for a Traveler who continued past disabled Minecraft', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);

    ActivityLog::create([
        'causer_id' => $user->id,
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'action' => 'onboarding_minecraft_disabled',
        'description' => 'Continued past disabled Minecraft step.',
        'meta' => [],
    ]);

    expect($user->currentOnboardingStep())->toBe('complete');
});

// ─── Migration backfill ───────────────────────────────────────────────────────

test('backfill: user with linked Discord account has dismissed_at set', function () {
    // The migration already ran — simulate by checking the state of a user
    // created BEFORE the migration by directly setting up accounts on a fresh user
    // and verifying the backfill logic via direct DB queries.
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'onboarding_wizard_dismissed_at' => null,
    ]);
    DiscordAccount::factory()->active()->for($user)->create();

    // Manually run the backfill logic (as if it were a new migration)
    $usersWithAccounts = \Illuminate\Support\Facades\DB::table('discord_accounts')
        ->select('user_id')
        ->union(\Illuminate\Support\Facades\DB::table('minecraft_accounts')->select('user_id'));

    \Illuminate\Support\Facades\DB::table('users')
        ->whereIn('id', $usersWithAccounts)
        ->whereNull('onboarding_wizard_dismissed_at')
        ->update(['onboarding_wizard_dismissed_at' => now()]);

    $user->refresh();

    expect($user->onboarding_wizard_dismissed_at)->not->toBeNull();
});

test('backfill: user with no linked accounts is left with dismissed_at null', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'onboarding_wizard_dismissed_at' => null,
    ]);

    // Run backfill
    $usersWithAccounts = \Illuminate\Support\Facades\DB::table('discord_accounts')
        ->select('user_id')
        ->union(\Illuminate\Support\Facades\DB::table('minecraft_accounts')->select('user_id'));

    \Illuminate\Support\Facades\DB::table('users')
        ->whereIn('id', $usersWithAccounts)
        ->whereNull('onboarding_wizard_dismissed_at')
        ->update(['onboarding_wizard_dismissed_at' => now()]);

    $user->refresh();

    expect($user->onboarding_wizard_dismissed_at)->toBeNull();
});
