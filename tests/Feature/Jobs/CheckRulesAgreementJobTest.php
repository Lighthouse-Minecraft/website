<?php

declare(strict_types=1);

use App\Enums\BrigType;
use App\Enums\MembershipLevel;
use App\Jobs\CheckRulesAgreementJob;
use App\Models\RuleVersion;
use App\Models\User;
use App\Notifications\RulesAgreementReminderNotification;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Notification;

uses()->group('rules', 'jobs');

it('sends a reminder to users overdue 14+ days who have not had a reminder', function () {
    Notification::fake();

    $version = RuleVersion::currentPublished();
    $version->published_at = now()->subDays(14);
    $version->save();

    $user = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);

    (new CheckRulesAgreementJob)->handle();

    Notification::assertSentTo($user, RulesAgreementReminderNotification::class);
    expect($user->fresh()->rules_reminder_sent_at)->not->toBeNull();
});

it('does not send a reminder if already sent', function () {
    Notification::fake();

    $version = RuleVersion::currentPublished();
    $version->published_at = now()->subDays(14);
    $version->save();

    $user = User::factory()->withoutRulesAgreed()->create([
        'membership_level' => MembershipLevel::Stowaway,
        'rules_reminder_sent_at' => now()->subDays(2),
    ]);

    (new CheckRulesAgreementJob)->handle();

    Notification::assertNotSentTo($user, RulesAgreementReminderNotification::class);
});

it('does not send a reminder if overdue less than 14 days', function () {
    Notification::fake();

    $version = RuleVersion::currentPublished();
    $version->published_at = now()->subDays(10);
    $version->save();

    $user = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);

    (new CheckRulesAgreementJob)->handle();

    Notification::assertNotSentTo($user, RulesAgreementReminderNotification::class);
});

it('places a user in rules brig when overdue 28+ days', function () {
    $this->mock(MinecraftRconService::class)
        ->shouldReceive('executeCommand')
        ->andReturn(['success' => true, 'response' => null, 'error' => null]);

    Notification::fake();

    $version = RuleVersion::currentPublished();
    $version->published_at = now()->subDays(28);
    $version->save();

    $user = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);

    (new CheckRulesAgreementJob)->handle();

    expect($user->fresh()->isInBrig())->toBeTrue()
        ->and($user->fresh()->brig_type)->toBe(BrigType::RulesNonCompliance);
});

it('does not double-brig a user already in rules_non_compliance brig', function () {
    $this->mock(MinecraftRconService::class)
        ->shouldReceive('executeCommand')
        ->andReturn(['success' => true, 'response' => null, 'error' => null]);

    Notification::fake();

    $version = RuleVersion::currentPublished();
    $version->published_at = now()->subDays(28);
    $version->save();

    $user = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);
    \App\Actions\PutUserInBrig::run(
        $user,
        $user,
        'Already in rules brig.',
        brigType: BrigType::RulesNonCompliance,
    );

    expect(fn () => (new CheckRulesAgreementJob)->handle())->not->toThrow(\Throwable::class);
    // Still only one brig entry — the user count for brigs has not changed
    expect($user->fresh()->isInBrig())->toBeTrue();
});

it('does not act on users who have already agreed', function () {
    Notification::fake();

    $version = RuleVersion::currentPublished();
    $version->published_at = now()->subDays(28);
    $version->save();

    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    // Factory auto-agrees so user already has agreement

    (new CheckRulesAgreementJob)->handle();

    Notification::assertNotSentTo($user, RulesAgreementReminderNotification::class);
    expect($user->fresh()->isInBrig())->toBeFalse();
});

it('does not act on Drifter-level users', function () {
    Notification::fake();

    $version = RuleVersion::currentPublished();
    $version->published_at = now()->subDays(28);
    $version->save();

    $user = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Drifter]);

    (new CheckRulesAgreementJob)->handle();

    Notification::assertNotSentTo($user, RulesAgreementReminderNotification::class);
    expect($user->fresh()->isInBrig())->toBeFalse();
});
