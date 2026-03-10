<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('dashboard', 'registration-answer');

it('shows registration answer in stowaway modal for authorized users', function () {
    $admin = loginAsAdmin();

    $stowaway = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
        'registration_question_text' => 'How did you find us?',
        'registration_answer' => 'Through a friend',
    ]);

    Volt::test('dashboard.stowaway-users-widget')
        ->call('viewUser', $stowaway->id)
        ->assertSee('Registration Question')
        ->assertSee('How did you find us?')
        ->assertSee('Through a friend');
});

it('does not show registration answer when user has none', function () {
    $admin = loginAsAdmin();

    $stowaway = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();

    Volt::test('dashboard.stowaway-users-widget')
        ->call('viewUser', $stowaway->id)
        ->assertDontSee('Registration Question');
});

it('shows registration answer card on profile for stowaway users', function () {
    $admin = loginAsAdmin();

    $stowaway = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
        'registration_question_text' => 'Why do you want to join?',
        'registration_answer' => 'I love the community',
    ]);

    Volt::test('users.registration-answer-card', ['user' => $stowaway])
        ->assertSee('Registration Response')
        ->assertSee('Why do you want to join?')
        ->assertSee('I love the community');
});

it('hides registration answer card for non-stowaway users', function () {
    $admin = loginAsAdmin();

    $traveler = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create([
        'registration_question_text' => 'How did you find us?',
        'registration_answer' => 'Google search',
    ]);

    Volt::test('users.registration-answer-card', ['user' => $traveler])
        ->assertDontSee('Registration Response');
});

it('hides registration answer card from unauthorized users', function () {
    $regularUser = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();
    loginAs($regularUser);

    $stowaway = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create([
        'registration_question_text' => 'How did you find us?',
        'registration_answer' => 'YouTube video',
    ]);

    Volt::test('users.registration-answer-card', ['user' => $stowaway])
        ->assertDontSee('Registration Response');
});
