<?php

declare(strict_types=1);

use App\Enums\BrigType;
use App\Models\User;

use function Pest\Laravel\actingAs;

uses()->group('parent-portal', 'brig');

it('shows parental pending message', function () {
    $user = User::factory()->create([
        'in_brig' => true,
        'brig_type' => BrigType::ParentalPending,
        'brig_reason' => 'Pending parental approval',
    ]);
    actingAs($user);

    \Livewire\Volt\Volt::test('dashboard.in-brig-card')
        ->assertSee('Account Pending Approval')
        ->assertSee('parental approval')
        ->assertDontSee('Submit Appeal');
});

it('shows parental disabled message', function () {
    $user = User::factory()->create([
        'in_brig' => true,
        'brig_type' => BrigType::ParentalDisabled,
        'brig_reason' => 'Site access restricted by parent.',
    ]);
    actingAs($user);

    \Livewire\Volt\Volt::test('dashboard.in-brig-card')
        ->assertSee('Account Restricted by Parent')
        ->assertDontSee('Submit Appeal');
});

it('shows age lock message', function () {
    $user = User::factory()->create([
        'in_brig' => true,
        'brig_type' => BrigType::AgeLock,
        'brig_reason' => 'Age verification required.',
    ]);
    actingAs($user);

    \Livewire\Volt\Volt::test('dashboard.in-brig-card')
        ->assertSee('Account Locked')
        ->assertSee('Age Verification Required')
        ->assertDontSee('Submit Appeal');
});

it('shows appeal button only for disciplinary brig', function () {
    $user = User::factory()->create([
        'in_brig' => true,
        'brig_type' => BrigType::Discipline,
        'brig_reason' => 'Bad behavior',
    ]);
    actingAs($user);

    \Livewire\Volt\Volt::test('dashboard.in-brig-card')
        ->assertSee('You Are In the Brig')
        ->assertSee('Submit Appeal');
});
