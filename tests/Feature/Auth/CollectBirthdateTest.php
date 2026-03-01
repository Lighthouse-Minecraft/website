<?php

declare(strict_types=1);

use App\Enums\BrigType;
use App\Models\User;
use App\Services\MinecraftRconService;

use function Pest\Laravel\actingAs;

uses()->group('parent-portal', 'auth');

it('redirects users without DOB to birthdate page', function () {
    $user = User::factory()->withoutDob()->create();
    actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('birthdate.show'));
});

it('does not redirect users with DOB', function () {
    $user = User::factory()->adult()->create();
    actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
});

it('releases age_lock for 17+', function () {
    $user = User::factory()->withoutDob()->create([
        'in_brig' => true,
        'brig_type' => BrigType::AgeLock,
        'brig_reason' => 'Age locked',
    ]);
    actingAs($user);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    \Livewire\Volt\Volt::test('auth.collect-birthdate')
        ->set('date_of_birth', now()->subYears(20)->format('Y-m-d'))
        ->call('submitDateOfBirth')
        ->assertRedirect(route('dashboard', absolute: false));

    $user->refresh();
    expect($user->in_brig)->toBeFalse()
        ->and($user->date_of_birth)->not->toBeNull();
});

it('puts existing user under 13 in parental_pending brig', function () {
    $user = User::factory()->withoutDob()->create();
    actingAs($user);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    \Livewire\Volt\Volt::test('auth.collect-birthdate')
        ->set('date_of_birth', now()->subYears(11)->format('Y-m-d'))
        ->call('submitDateOfBirth')
        ->assertSet('step', 2)
        ->set('parent_email', 'parent@example.com')
        ->call('submitParentEmail')
        ->assertRedirect(route('dashboard', absolute: false));

    $user->refresh();
    expect($user->in_brig)->toBeTrue()
        ->and($user->brig_type)->toBe(BrigType::ParentalPending)
        ->and($user->parent_email)->toBe('parent@example.com');
});

it('collects parent email for 13-16 and redirects', function () {
    $user = User::factory()->withoutDob()->create();
    actingAs($user);

    \Livewire\Volt\Volt::test('auth.collect-birthdate')
        ->set('date_of_birth', now()->subYears(15)->format('Y-m-d'))
        ->call('submitDateOfBirth')
        ->assertSet('step', 2)
        ->set('parent_email', 'parent@example.com')
        ->call('submitParentEmail')
        ->assertRedirect(route('dashboard', absolute: false));

    $user->refresh();
    expect($user->in_brig)->toBeFalse()
        ->and($user->parent_email)->toBe('parent@example.com');
});
