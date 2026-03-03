<?php

declare(strict_types=1);

use App\Enums\BrigType;
use App\Models\User;
use App\Services\MinecraftRconService;
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
        ->assertSee('Parent or Guardian Email');
});

it('puts under 13 in brig with parental_pending type', function () {
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    Volt::test('auth.register')
        ->set('name', 'Young User')
        ->set('email', 'young@example.com')
        ->set('date_of_birth', now()->subYears(11)->format('Y-m-d'))
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->set('parent_email', 'parent@example.com')
        ->call('submitParentEmail');

    $user = User::where('email', 'young@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->in_brig)->toBeTrue()
        ->and($user->brig_type)->toBe(BrigType::ParentalPending)
        ->and($user->parent_allows_site)->toBeFalse()
        ->and($user->parent_email)->toBe('parent@example.com');
});

it('logs in under 13 user but puts them in brig', function () {
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    Volt::test('auth.register')
        ->set('name', 'Young User')
        ->set('email', 'young2@example.com')
        ->set('date_of_birth', now()->subYears(10)->format('Y-m-d'))
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->set('parent_email', 'parent@example.com')
        ->call('submitParentEmail')
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    $user = User::where('email', 'young2@example.com')->first();
    expect($user->in_brig)->toBeTrue()
        ->and($user->brig_type)->toBe(BrigType::ParentalPending);
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
        ->call('submitParentEmail')
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
