<?php

declare(strict_types=1);

use App\Actions\AgreeToRulesVersion;
use App\Enums\MembershipLevel;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses()->group('rules', 'dashboard-gate');

it('redirects a user who has not agreed to the rules to the rules page', function () {
    $user = User::factory()->withoutRulesAgreed()->create(['membership_level' => MembershipLevel::Stowaway]);

    actingAs($user);

    get(route('dashboard'))->assertRedirect(route('rules.show'));
});

it('allows a user who has agreed to the current version to access the dashboard', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    AgreeToRulesVersion::run($user, $user);

    actingAs($user);

    get(route('dashboard'))->assertOk();
});

it('allows access to the rules page without having agreed', function () {
    $user = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);

    actingAs($user);

    get(route('rules.show'))->assertOk();
});
