<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Models\ActivityLog;
use App\Models\MinecraftAccount;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

// ─── Minecraft step visibility ────────────────────────────────────────────────

test('Traveler with no Minecraft account sees the Minecraft step card', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSet('step', 'minecraft')
        ->assertSee('Link Your Minecraft Account');
});

test('Connect Minecraft button points to the minecraft account settings route', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSee(route('settings.minecraft-accounts'));
});

test('Minecraft step shows Skip for now option', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSee('Skip for now')
        ->assertSee('Connect Minecraft');
});

// ─── Skip Minecraft ───────────────────────────────────────────────────────────

test('Skip for now on Minecraft step records onboarding_minecraft_skipped', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->call('skipMinecraft');

    expect(
        ActivityLog::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('action', 'onboarding_minecraft_skipped')
            ->exists()
    )->toBeTrue();
});

test('Skip for now on Minecraft step triggers completion', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->call('skipMinecraft')
        ->assertSet('step', 'complete')
        ->assertSet('showWelcomeModal', true);

    expect($user->fresh()->onboarding_wizard_completed_at)->not->toBeNull()
        ->and($user->fresh()->onboarding_wizard_dismissed_at)->not->toBeNull();
});

// ─── Parent-disabled Minecraft state ─────────────────────────────────────────

test('parent-disabled Minecraft state shows explanation and Continue button', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
        'parent_allows_minecraft' => false,
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSee('Minecraft is currently disabled')
        ->assertSee('Continue')
        ->assertDontSee('Skip for now');
});

test('Continue on parent-disabled Minecraft records onboarding_minecraft_disabled', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
        'parent_allows_minecraft' => false,
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->call('continueDisabledMinecraft');

    expect(
        ActivityLog::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('action', 'onboarding_minecraft_disabled')
            ->exists()
    )->toBeTrue();
});

test('Continue on parent-disabled Minecraft triggers completion', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
        'parent_allows_minecraft' => false,
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->call('continueDisabledMinecraft')
        ->assertSet('step', 'complete')
        ->assertSet('showWelcomeModal', true);
});

// ─── Completion action ────────────────────────────────────────────────────────

test('completion sets both completed_at and dismissed_at', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->call('skipMinecraft');

    $user->refresh();
    expect($user->onboarding_wizard_completed_at)->not->toBeNull()
        ->and($user->onboarding_wizard_dismissed_at)->not->toBeNull();
});

test('completion records onboarding_wizard_completed in activity log', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->call('skipMinecraft');

    expect(
        ActivityLog::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('action', 'onboarding_wizard_completed')
            ->exists()
    )->toBeTrue();
});

test('wizard auto-completes when Traveler already has Minecraft linked', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
    ]);
    MinecraftAccount::factory()->active()->for($user)->create();
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->assertSet('step', 'complete')
        ->assertSet('showWelcomeModal', true);

    expect($user->fresh()->onboarding_wizard_completed_at)->not->toBeNull();
});

// ─── Welcome modal ────────────────────────────────────────────────────────────

test('welcome modal is shown after completion', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->call('skipMinecraft')
        ->assertSee('Welcome to Lighthouse!');
});

test('welcome modal contains feature highlights and notification settings link', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->call('skipMinecraft')
        ->assertSee('Join Discussions')
        ->assertSee('Open a Ticket')
        ->assertSee('notification preferences')
        ->assertSee(route('settings.notifications'));
});

test('welcome modal is NOT shown on dismissal', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    Volt::test('onboarding.wizard')
        ->call('dismiss')
        ->assertDontSee('Welcome to Lighthouse!');
});

// ─── Sidebar "Resume Account Setup" link ─────────────────────────────────────

test('sidebar shows Resume Account Setup link when wizard is active', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    get('dashboard')->assertSee('Resume Account Setup');
});

test('sidebar does not show Resume Account Setup link after wizard is dismissed', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_accepted_at' => now(),
        'onboarding_wizard_dismissed_at' => now(),
    ]);
    loginAs($user);

    get('dashboard')->assertDontSee('Resume Account Setup');
});

test('sidebar does not show Resume Account Setup link after wizard is completed', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'rules_accepted_at' => now(),
        'onboarding_wizard_completed_at' => now(),
        'onboarding_wizard_dismissed_at' => now(),
    ]);
    loginAs($user);

    get('dashboard')->assertDontSee('Resume Account Setup');
});
