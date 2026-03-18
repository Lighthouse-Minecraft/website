<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('parent-portal', 'auth');

it('registers 17+ user normally', function () {
    $component = Volt::test('auth.register')
        ->set('name', 'Adult User')
        ->set('email', 'adult@example.com')
        ->set('date_of_birth', now()->subYears(20)->format('Y-m-d'))
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $component
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect(User::where('email', 'adult@example.com')->first()->date_of_birth->format('Y-m-d'))
        ->toBe(now()->subYears(20)->format('Y-m-d'));
});

it('shows parent email step for under 17', function () {
    $component = Volt::test('auth.register')
        ->set('name', 'Teen User')
        ->set('email', 'teen@example.com')
        ->set('date_of_birth', now()->subYears(15)->format('Y-m-d'))
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $component->assertHasNoErrors()
        ->assertSet('step', 2)
        ->assertSee('Parent/Guardian Email');
});

it('does not create account for under 13 and shows parent notification step', function () {
    Volt::test('auth.register')
        ->set('name', 'Young User')
        ->set('email', 'young@example.com')
        ->set('date_of_birth', now()->subYears(11)->format('Y-m-d'))
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->set('parent_email', 'parent@example.com')
        ->call('submitStep2')
        ->assertSet('step', 3)
        ->assertSee('Emailed Your Parent');

    // No account should be created for under-13 users (COPPA compliance)
    $user = User::where('email', 'young@example.com')->first();
    expect($user)->toBeNull();

    $this->assertGuest();
});

it('does not log in under 13 user and does not redirect to dashboard', function () {
    Volt::test('auth.register')
        ->set('name', 'Young User')
        ->set('email', 'young2@example.com')
        ->set('date_of_birth', now()->subYears(10)->format('Y-m-d'))
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->set('parent_email', 'parent@example.com')
        ->call('submitStep2')
        ->assertSet('step', 3)
        ->assertNoRedirect();

    $this->assertGuest();
    $user = User::where('email', 'young2@example.com')->first();
    expect($user)->toBeNull();
});

it('logs in 13-16 user after registration', function () {
    Volt::test('auth.register')
        ->set('name', 'Teen User')
        ->set('email', 'teen2@example.com')
        ->set('date_of_birth', now()->subYears(15)->format('Y-m-d'))
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->set('parent_email', 'parent@example.com')
        ->call('submitStep2')
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
