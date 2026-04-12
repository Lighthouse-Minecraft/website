<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\UserRuleAgreement;

uses()->group('rules', 'compliance');

it('compliance query returns users who have not agreed to the current version', function () {
    $version = RuleVersion::currentPublished();

    $agreedUser = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    $unagreedUser = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);

    $agreedUserIds = $version->agreements()->pluck('user_id');
    $nonAgreed = User::whereNotIn('id', $agreedUserIds)
        ->where('membership_level', '>=', MembershipLevel::Stowaway->value)
        ->pluck('id');

    expect($nonAgreed)->toContain($unagreedUser->id)
        ->and($nonAgreed)->not->toContain($agreedUser->id);
});

it('compliance query excludes Drifter users', function () {
    $version = RuleVersion::currentPublished();

    $drifter = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Drifter]);

    $agreedUserIds = $version->agreements()->pluck('user_id');
    $nonAgreed = User::whereNotIn('id', $agreedUserIds)
        ->where('membership_level', '>=', MembershipLevel::Stowaway->value)
        ->pluck('id');

    expect($nonAgreed)->not->toContain($drifter->id);
});

it('agreement history returns all user_rule_agreements records', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    // Factory auto-creates agreement for current version

    $version = RuleVersion::currentPublished();
    $agreements = UserRuleAgreement::with(['user', 'ruleVersion'])->get();

    $userAgreement = $agreements->firstWhere('user_id', $user->id);
    expect($userAgreement)->not->toBeNull()
        ->and($userAgreement->rule_version_id)->toBe($version->id)
        ->and($userAgreement->agreed_at)->not->toBeNull();
});
