<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Models\DiscordAccount;
use App\Models\MinecraftAccount;
use App\Models\User;

use function Pest\Laravel\get;

// ─── Widget visibility ────────────────────────────────────────────────────────

test('widget shows for a Traveler with no Discord and no Minecraft linked', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'onboarding_wizard_dismissed_at' => now(),
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    get('dashboard')
        ->assertSee('Complete Your Setup')
        ->assertSee('Link Discord')
        ->assertSee('Link Minecraft Account');
});

test('widget shows for a Stowaway with no Discord linked', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'onboarding_wizard_dismissed_at' => now(),
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    get('dashboard')
        ->assertSee('Complete Your Setup')
        ->assertSee('Link Discord');
});

test('widget does not show when wizard is active', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'onboarding_wizard_dismissed_at' => null,
        'onboarding_wizard_completed_at' => null,
    ]);
    loginAs($user);

    get('dashboard')->assertDontSee('Complete Your Setup');
});

test('widget does not show when all applicable accounts are linked', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    DiscordAccount::factory()->active()->for($user)->create();
    MinecraftAccount::factory()->active()->for($user)->create();
    $user->update(['onboarding_wizard_dismissed_at' => now()]);
    loginAs($user);

    get('dashboard')->assertDontSee('Complete Your Setup');
});

test('widget does not show for a Drifter', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Drifter]);
    loginAs($user);

    get('dashboard')->assertDontSee('Complete Your Setup');
});

// ─── Discord section ──────────────────────────────────────────────────────────

test('Discord section is hidden when Discord is already linked', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'onboarding_wizard_dismissed_at' => now(),
        'rules_accepted_at' => now(),
    ]);
    DiscordAccount::factory()->active()->for($user)->create();
    loginAs($user);

    // Widget shows (Minecraft still unlinked) but Discord section is hidden
    get('dashboard')
        ->assertSee('Complete Your Setup')
        ->assertDontSee('Link Discord');
});

test('Discord section is hidden when parent_allows_discord is false', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'parent_allows_discord' => false,
        'onboarding_wizard_dismissed_at' => now(),
    ]);
    loginAs($user);

    // Discord section hidden; Minecraft may still show
    get('dashboard')->assertDontSee('Link Discord');
});

// ─── Minecraft section ────────────────────────────────────────────────────────

test('Minecraft section is hidden when Minecraft is already linked', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'onboarding_wizard_dismissed_at' => now(),
        'rules_accepted_at' => now(),
    ]);
    MinecraftAccount::factory()->active()->for($user)->create();
    loginAs($user);

    // Widget shows (Discord still unlinked) but Minecraft section hidden
    get('dashboard')
        ->assertSee('Complete Your Setup')
        ->assertDontSee('Link Minecraft Account');
});

test('Minecraft section is hidden for a Stowaway (not yet Traveler)', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'onboarding_wizard_dismissed_at' => now(),
    ]);
    loginAs($user);

    get('dashboard')->assertDontSee('Link Minecraft Account');
});

test('Minecraft section is hidden when parent_allows_minecraft is false', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'parent_allows_minecraft' => false,
        'onboarding_wizard_dismissed_at' => now(),
    ]);
    loginAs($user);

    get('dashboard')->assertDontSee('Link Minecraft Account');
});

// ─── CTA labels and links ─────────────────────────────────────────────────────

test('Link Discord button points to the discord account settings page', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'onboarding_wizard_dismissed_at' => now(),
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    get('dashboard')->assertSee(route('settings.discord-account'), false);
});

test('Link Minecraft Account button points to the minecraft account settings page', function () {
    $user = User::factory()->create([
        'membership_level' => MembershipLevel::Traveler,
        'onboarding_wizard_dismissed_at' => now(),
        'rules_accepted_at' => now(),
    ]);
    loginAs($user);

    get('dashboard')->assertSee(route('settings.minecraft-accounts'), false);
});
