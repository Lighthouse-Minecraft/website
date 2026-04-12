<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;

uses()->group('brig', 'livewire');

it('shows permanent checkbox in brig modal', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create();
    actingAs($warden);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $target])
        ->assertSee('Permanent');
});

it('places user in permanent brig when permanent checkbox is checked', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create();
    actingAs($warden);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $target])
        ->set('brigActionReason', 'Permanent ban for serious violation')
        ->set('brigActionPermanent', true)
        ->call('confirmPutInBrig');

    expect($target->fresh()->in_brig)->toBeTrue()
        ->and($target->fresh()->brig_expires_at)->toBeNull();
});

it('places user in timed brig when permanent is unchecked and days are set', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create();
    actingAs($warden);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $target])
        ->set('brigActionReason', 'Short sentence for minor infraction')
        ->set('brigActionPermanent', false)
        ->set('brigActionDays', 7)
        ->call('confirmPutInBrig');

    expect($target->fresh()->in_brig)->toBeTrue()
        ->and($target->fresh()->brig_expires_at)->not->toBeNull();
});

it('places user in brig with no expiry when permanent is unchecked and no days set', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create();
    actingAs($warden);

    Livewire\Volt\Volt::test('users.display-basic-details', ['user' => $target])
        ->set('brigActionReason', 'No timer set for this sentence')
        ->set('brigActionPermanent', false)
        ->set('brigActionDays', null)
        ->call('confirmPutInBrig');

    expect($target->fresh()->in_brig)->toBeTrue()
        ->and($target->fresh()->brig_expires_at)->toBeNull();
});
