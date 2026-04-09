<?php

declare(strict_types=1);

use App\Actions\AgreeToRulesVersion;
use App\Enums\BrigType;
use App\Enums\MembershipLevel;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\UserRuleAgreement;
use App\Services\MinecraftRconService;

uses()->group('rules', 'proxy-agreement');

it('records proxy_user_id when parent agrees on behalf of child', function () {
    $parent = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    $child = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);
    $parent->children()->attach($child->id);

    $version = RuleVersion::currentPublished();

    AgreeToRulesVersion::run($child, $parent);

    $agreement = UserRuleAgreement::where('user_id', $child->id)
        ->where('rule_version_id', $version->id)
        ->first();

    expect($agreement)->not->toBeNull()
        ->and($agreement->proxy_user_id)->toBe($parent->id)
        ->and($agreement->isProxy())->toBeTrue();
});

it('does not set proxy_user_id when user agrees for themselves', function () {
    $user = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);
    $version = RuleVersion::currentPublished();

    AgreeToRulesVersion::run($user, $user);

    $agreement = UserRuleAgreement::where('user_id', $user->id)
        ->where('rule_version_id', $version->id)
        ->first();

    expect($agreement->proxy_user_id)->toBeNull()
        ->and($agreement->isProxy())->toBeFalse();
});

it('promotes Drifter child to Stowaway when parent agrees on their behalf', function () {
    $parent = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    $child = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Drifter]);
    $parent->children()->attach($child->id);

    AgreeToRulesVersion::run($child, $parent);

    expect($child->fresh()->membership_level)->toBe(MembershipLevel::Stowaway);
});

it('lifts rules_non_compliance brig when parent agrees on behalf of child', function () {
    $this->mock(MinecraftRconService::class)
        ->shouldReceive('executeCommand')
        ->andReturn(['success' => true, 'response' => null, 'error' => null]);

    $parent = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    $child = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);
    $parent->children()->attach($child->id);

    \App\Actions\PutUserInBrig::run(
        $child,
        $child,
        'Rules non-compliance brig.',
        brigType: BrigType::RulesNonCompliance,
    );

    expect($child->fresh()->isInBrig())->toBeTrue();

    AgreeToRulesVersion::run($child, $parent);

    expect($child->fresh()->isInBrig())->toBeFalse();
});

it('unagreedChildren returns only children who have not agreed', function () {
    $parent = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    $agreedChild = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    $unagreedChild = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);

    $parent->children()->attach($agreedChild->id);
    $parent->children()->attach($unagreedChild->id);

    $unagreed = $parent->unagreedChildren();

    expect($unagreed->pluck('id'))->toContain($unagreedChild->id)
        ->and($unagreed->pluck('id'))->not->toContain($agreedChild->id);
});

it('dashboard gate redirects parent with unagreed children to rules page', function () {
    $parent = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    $child = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);
    $parent->children()->attach($child->id);

    $this->actingAs($parent)
        ->get(route('dashboard'))
        ->assertRedirect(route('rules.show'));
});

it('dashboard gate allows parent through when all children have agreed', function () {
    $parent = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    $child = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    $parent->children()->attach($child->id);

    $this->actingAs($parent)
        ->get(route('dashboard'))
        ->assertOk();
});

it('proxy agreement does not cross-contaminate: agreeing for one child does not agree for another', function () {
    $parent = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    $child1 = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);
    $child2 = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);
    $parent->children()->attach($child1->id);
    $parent->children()->attach($child2->id);

    AgreeToRulesVersion::run($child1, $parent);

    expect($child1->fresh()->hasAgreedToCurrentRules())->toBeTrue()
        ->and($child2->fresh()->hasAgreedToCurrentRules())->toBeFalse();
});
