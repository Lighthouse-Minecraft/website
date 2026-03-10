<?php

declare(strict_types=1);

use App\Models\SiteConfig;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('auth', 'registration-question');

it('skips step 2 for adult users when no registration question is configured', function () {
    // No registration_question in DB — adult should go straight through
    Volt::test('auth.register')
        ->set('name', 'Adult User')
        ->set('email', 'adult@example.com')
        ->set('date_of_birth', now()->subYears(20)->format('Y-m-d'))
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

it('shows step 2 for adult users when registration question is configured', function () {
    SiteConfig::create(['key' => 'registration_question', 'value' => 'How did you find us?']);

    Volt::test('auth.register')
        ->set('name', 'Adult User')
        ->set('email', 'adult2@example.com')
        ->set('date_of_birth', now()->subYears(20)->format('Y-m-d'))
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasNoErrors()
        ->assertSet('step', 2);
});

it('saves registration answer and question text on account creation', function () {
    SiteConfig::create(['key' => 'registration_question', 'value' => 'How did you find us?']);

    Volt::test('auth.register')
        ->set('name', 'New User')
        ->set('email', 'newuser@example.com')
        ->set('date_of_birth', now()->subYears(20)->format('Y-m-d'))
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->set('registration_answer', 'Found you on Google!')
        ->call('submitStep2')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'newuser@example.com')->first();
    expect($user->registration_question_text)->toBe('How did you find us?')
        ->and($user->registration_answer)->toBe('Found you on Google!');
});

it('requires registration answer when question is configured', function () {
    SiteConfig::create(['key' => 'registration_question', 'value' => 'How did you find us?']);

    Volt::test('auth.register')
        ->set('name', 'New User')
        ->set('email', 'newuser2@example.com')
        ->set('date_of_birth', now()->subYears(20)->format('Y-m-d'))
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->call('submitStep2')
        ->assertHasErrors(['registration_answer']);
});

it('still requires parent email for under 17 users', function () {
    SiteConfig::create(['key' => 'registration_question', 'value' => 'How did you find us?']);

    Volt::test('auth.register')
        ->set('name', 'Teen User')
        ->set('email', 'teen@example.com')
        ->set('date_of_birth', now()->subYears(15)->format('Y-m-d'))
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->set('registration_answer', 'My friend told me')
        ->call('submitStep2')
        ->assertHasErrors(['parent_email']);
});

it('does not save registration answer fields when no question configured', function () {
    Volt::test('auth.register')
        ->set('name', 'No Question User')
        ->set('email', 'noquestion@example.com')
        ->set('date_of_birth', now()->subYears(20)->format('Y-m-d'))
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'noquestion@example.com')->first();
    expect($user->registration_question_text)->toBeNull()
        ->and($user->registration_answer)->toBeNull();
});

it('saves both parent email and registration answer for under 17 with question', function () {
    SiteConfig::create(['key' => 'registration_question', 'value' => 'Why do you want to join?']);

    Volt::test('auth.register')
        ->set('name', 'Teen With Answer')
        ->set('email', 'teenanswer@example.com')
        ->set('date_of_birth', now()->subYears(15)->format('Y-m-d'))
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->set('parent_email', 'parent@example.com')
        ->set('registration_answer', 'I love Minecraft and want a safe community')
        ->call('submitStep2')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'teenanswer@example.com')->first();
    expect($user->parent_email)->toBe('parent@example.com')
        ->and($user->registration_question_text)->toBe('Why do you want to join?')
        ->and($user->registration_answer)->toBe('I love Minecraft and want a safe community');
});

it('skips step 2 for adult when registration question is empty string', function () {
    SiteConfig::create(['key' => 'registration_question', 'value' => '']);

    Volt::test('auth.register')
        ->set('name', 'Empty Q User')
        ->set('email', 'emptyq@example.com')
        ->set('date_of_birth', now()->subYears(20)->format('Y-m-d'))
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertRedirect(route('dashboard', absolute: false));
});
