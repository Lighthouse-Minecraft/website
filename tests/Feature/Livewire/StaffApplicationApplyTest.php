<?php

declare(strict_types=1);

use App\Models\ApplicationQuestion;
use App\Models\StaffPosition;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses()->group('applications', 'livewire');

it('loads the apply page for accepting position', function () {
    $user = User::factory()->create();
    actingAs($user);
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);

    ApplicationQuestion::factory()->create(['category' => 'core', 'is_active' => true]);

    Livewire::test('staff-applications.apply', ['staffPosition' => $position])
        ->assertStatus(200);
});

it('blocks application when position not accepting', function () {
    $user = User::factory()->create();
    actingAs($user);
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => false]);

    Livewire::test('staff-applications.apply', ['staffPosition' => $position])
        ->assertStatus(403);
});

it('blocks application for user in brig', function () {
    $user = User::factory()->create(['in_brig' => true]);
    actingAs($user);
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);

    Livewire::test('staff-applications.apply', ['staffPosition' => $position])
        ->assertStatus(403);
});
