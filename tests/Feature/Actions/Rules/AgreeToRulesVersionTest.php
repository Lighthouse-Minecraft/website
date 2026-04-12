<?php

declare(strict_types=1);

use App\Actions\AgreeToRulesVersion;
use App\Actions\PutUserInBrig;
use App\Enums\BrigType;
use App\Enums\MembershipLevel;
use App\Models\RuleVersion;
use App\Models\User;
use App\Services\MinecraftRconService;

uses()->group('rules', 'actions', 'agreement');

it('records a user_rule_agreements entry for the current published version', function () {
    $user = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);

    AgreeToRulesVersion::run($user, $user);

    $version = RuleVersion::currentPublished();
    expect($user->ruleAgreements()->where('rule_version_id', $version->id)->exists())->toBeTrue();
});

it('promotes a Drifter to Stowaway when they agree', function () {
    $user = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Drifter]);

    AgreeToRulesVersion::run($user, $user);

    expect($user->fresh()->membership_level)->toBe(MembershipLevel::Stowaway);
});

it('does not promote a non-Drifter when they agree', function () {
    $user = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Traveler]);

    AgreeToRulesVersion::run($user, $user);

    expect($user->fresh()->membership_level)->toBe(MembershipLevel::Traveler);
});

it('auto-lifts a rules_non_compliance brig when the user agrees', function () {
    $this->mock(MinecraftRconService::class)
        ->shouldReceive('executeCommand')
        ->andReturn(['success' => true, 'response' => null, 'error' => null]);

    $admin = User::factory()->create();
    $user = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);

    PutUserInBrig::run($user, $admin, 'Non-compliance with rules.', brigType: BrigType::RulesNonCompliance);
    expect($user->fresh()->isInBrig())->toBeTrue();

    AgreeToRulesVersion::run($user, $user);

    expect($user->fresh()->isInBrig())->toBeFalse();
});

it('does not lift a non-rules brig when the user agrees', function () {
    $this->mock(MinecraftRconService::class)
        ->shouldReceive('executeCommand')
        ->andReturn(['success' => true, 'response' => null, 'error' => null]);

    $admin = User::factory()->create();
    $user = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);

    PutUserInBrig::run($user, $admin, 'Disciplinary action.');
    expect($user->fresh()->isInBrig())->toBeTrue();

    AgreeToRulesVersion::run($user, $user);

    expect($user->fresh()->isInBrig())->toBeTrue();
});

it('is idempotent when called multiple times', function () {
    $user = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);

    AgreeToRulesVersion::run($user, $user);
    AgreeToRulesVersion::run($user, $user);

    $version = RuleVersion::currentPublished();
    expect($user->ruleAgreements()->where('rule_version_id', $version->id)->count())->toBe(1);
});
